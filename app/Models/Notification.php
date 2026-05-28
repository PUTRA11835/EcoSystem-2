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

    protected static function booted(): void
    {
        static::created(function (self $notification) {
            // Send Web Push after the HTTP response so it doesn't add latency
            app()->terminating(function () use ($notification) {
                try {
                    $payload = [
                        'title' => self::buildTitle($notification),
                        'body'  => $notification->preview ?? '',
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

    private static function buildTitle(self $n): string
    {
        $from = $n->from_name ?: 'Someone';
        return match ($n->type) {
            'timesheet_submitted'          => $from . ' submitted a timesheet',
            'late_exception_submitted'     => 'Late Access Request from ' . $from,
            'late_exception_pending_rpmo'  => 'Late Access Request needs your review',
            'late_exception_head_approved' => 'Late Access approved by Head',
            'late_exception_head_rejected' => 'Late Access rejected by Head',
            'late_exception_approved'      => 'Late Access Request approved',
            'late_exception_rejected'      => 'Late Access Request rejected',
            'customer_mandays_canceled'    => 'Mandays Proposal canceled',
            'customer_mandays_proposed'    => 'Mandays Proposal — needs review',
            'resolution_days_proposed'     => 'Resolution Days — needs review',
            'ticket_member_added'          => $from . ' added you to a ticket',
            'ticket_member_removed'        => $from . ' removed a member from ticket',
            'ticket_member_reactivated'    => $from . ' re-added you to a ticket',
            'ticket_internal_note'         => $from . ' added an internal note',
            'ticket_reply'                 => $from . ' replied to a ticket',
            default                        => $from . ' sent you a notification',
        };
    }
}
