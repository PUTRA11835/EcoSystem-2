<?php

namespace App\Models;

use App\Services\WebPushService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = [
        'employee_id',
        'customer_id',
        'type',
        'ticket_id',
        'message_id',
        'from_employee_id',
        'from_name',
        'preview',
        'link',
        'is_read',
        'read_at',
    ];

    protected $casts = [
        'is_read' => 'boolean',
        'read_at' => 'datetime',
    ];

    public function ticket()
    {
        return $this->belongsTo(\App\Models\Ticket::class, 'ticket_id', 'ticket_id');
    }

    protected static function booted(): void
    {
        static::creating(function (self $notification) {
            if (blank($notification->from_name) && $notification->from_employee_id) {
                try {
                    $sender = \App\Models\Employee::with('basicData')->find($notification->from_employee_id);
                    $notification->from_name = $sender?->fullNameLabel();
                } catch (\Throwable) {
                    // the name is a nicety; the notification is still sent
                }
            }
        });

        static::created(function (self $notification) {
            // Customer notifications (Jarvies bell) don't use Web Push
            if (!$notification->employee_id) return;

            // Send Web Push after the HTTP response so it doesn't add latency
            app()->terminating(function () use ($notification) {
                try {
                    $metaParts = [];
                    if ($notification->ticket_id) {
                        $ticket = \App\Models\Ticket::select('ticket_id', 'ticket_number', 'customer_id')
                            ->with('customer:customer_id,customer_code')
                            ->find($notification->ticket_id);
                        if ($ticket?->ticket_number) $metaParts[] = $ticket->ticket_number;
                        if ($ticket?->customer?->customer_code) $metaParts[] = $ticket->customer->customer_code;
                    }
                    $meta  = $metaParts ? implode(' · ', $metaParts) . "\n" : '';
                    $payload = [
                        'title' => self::buildTitle($notification),
                        'body'  => $meta . ($notification->preview ?? ''),
                        'url'   => $notification->link ?? '/',
                        'tag'   => 'ecosystem-' . $notification->type . '-' . $notification->id,
                        'type'  => $notification->type,
                    ];
                    app(WebPushService::class)->sendToEmployee((int) $notification->employee_id, $payload);
                } catch (\Throwable $e) {
                    Log::warning('WebPush dispatch failed', ['error' => $e->getMessage()]);
                }
            });
        });
    }

    /**
     * The headline of a notification, in one place — the bell, the Notifications page and web push all use it.
     *
     * A person is named only when there is one: a mention, a reply, an approver acting. A system notice (a reminder, "needs your
     * approval" without a sender) says what happened instead — it is never presented as someone mentioning you.
     */
    public static function titleFor(?string $type, ?string $fromName = null): string
    {
        $from = trim((string) $fromName);
        $who  = $from !== '' ? $from : 'Someone';

        // Approval workflows: <module>_{pending_approval|approved|rejected|progressed}
        $modules = [
            'cash_advance_report' => 'cash advance report', 'cash_advance' => 'cash advance', 'purchase_request' => 'purchase request',
            'reimbursement' => 'reimbursement', 'overtime' => 'overtime request',
        ];
        foreach ($modules as $prefix => $label) {
            if (str_starts_with((string) $type, $prefix . '_')) {
                $outcome = substr($type, strlen($prefix) + 1);
                $cap = ucfirst($label);
                $article = in_array($label[0], ['a', 'e', 'i', 'o', 'u'], true) ? 'an' : 'a';

                $title = match ($outcome) {
                    'pending_approval' => $from !== '' ? "{$from} sent {$article} {$label} for your approval" : "{$cap} needs your approval",
                    'approved'         => $from !== '' ? "{$from} approved your {$label}" : "Your {$label} was approved",
                    'rejected'         => $from !== '' ? "{$from} rejected your {$label}" : "Your {$label} was rejected",
                    'progressed'       => $from !== '' ? "{$from} moved your {$label} to the next review step" : "Your {$label} moved to the next review step",
                    default            => null,
                };
                if ($title !== null) {
                    return $title;
                }
            }
        }

        return match ($type) {
            'timesheet_submitted'          => ($from ?: 'A consultant') . ' submitted a timesheet',
            'late_exception_submitted'     => 'Late Access Request from ' . ($from ?: 'an employee'),
            'late_exception_pending_rpmo'  => 'Late Access Request needs your review',
            'late_exception_head_approved' => 'Late Access approved by Head',
            'late_exception_head_rejected' => 'Late Access rejected by Head',
            'late_exception_approved'      => 'Late Access Request approved',
            'late_exception_rejected'      => 'Late Access Request rejected',
            'customer_mandays_canceled'    => 'Mandays Proposal canceled',
            'customer_mandays_proposed'    => 'Mandays Proposal — needs review',
            'resolution_days_proposed'     => 'Resolution Days — needs review',
            'contract_end_reminder'        => 'Contract deadline reminder',
            'top_invoice_reminder'         => 'Invoice submission due',
            'ticket_member_added'          => $who . ' added you to a ticket',
            'ticket_member_removed'        => $who . ' removed a member from a ticket',
            'ticket_member_reactivated'    => $who . ' re-added a member to a ticket',
            'ticket_internal_note'         => $who . ' added an internal note',
            'ticket_reply'                 => $who . ' replied to a ticket',
            'customer_email_reply'         => ($from ?: 'A customer') . ' replied via email',
            'mention'                      => $who . ' mentioned you',
            'leave_permit_submitted'       => ($from ?: 'An employee') . ' submitted a Leave/Permit request for approval',
            'leave_permit_approved'        => 'Your Leave/Permit request was approved',
            'leave_permit_rejected'        => 'Your Leave/Permit request was rejected',
            'leave_permit_revision'        => 'Revision requested for your Leave/Permit request',
            'letter_request_new'           => ($from ?: 'An employee') . ' asked for a letter',
            'letter_request_done'          => 'Your requested letter is ready',
            'letter_request_rejected'      => 'Your letter request was rejected',
            'join_date_reminder'           => 'HR needs your join date',
            'attendance_correction_pending_approval' => ($from ?: 'An employee') . ' requested an attendance correction',
            'attendance_correction_approved'         => 'Your attendance correction was approved',
            'attendance_correction_rejected'         => 'Your attendance correction was rejected',
            'contract_sign_request'        => 'A contract is waiting for your signature',
            'contract_sign_forwarded'      => 'A contract needs your approval',
            'contract_signed'              => 'A contract was signed',
            'contract_revision'            => 'A change was requested on a contract',
            'contract_released'            => 'Your contract is ready to download',
            'contract_sign_reminder'       => 'Contract signing reminder',
            'offboarding_task_due'         => 'An offboarding task is due',
            'external_timesheet_pending_approval' => 'An external timesheet needs your approval',
            'external_timesheet_approved'  => 'Your timesheet was approved — upload your invoice',
            'external_timesheet_returned'  => 'Your timesheet was returned for correction',
            'external_invoice_pending_verification' => 'An external invoice needs your verification',
            'external_invoice_verified'    => 'Your invoice was verified',
            'external_invoice_rejected'    => 'Your invoice was rejected',
            'external_payment_paid'        => 'Your invoice was paid',
            'reimbursement_paid'           => 'Your reimbursement was paid',
            'reimbursement_payment_reversed' => 'The payment of your reimbursement was reversed',
            default                        => $from !== '' ? $from . ' sent you a notification' : 'New notification',
        };
    }

    private static function buildTitle(self $n): string
    {
        return self::titleFor($n->type, $n->from_name);
    }

    /** The headline, for views that hold the model. */
    public function getTitleAttribute(): string
    {
        return self::titleFor($this->type, $this->from_name);
    }
}
