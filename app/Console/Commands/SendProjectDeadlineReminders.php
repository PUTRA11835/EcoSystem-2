<?php

namespace App\Console\Commands;

use App\Enums\RoleId;
use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPaymentTerm;
use App\Models\Employee;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Daily reminders for the Head of Project & Delivery Project Administrator roles.
 *
 * Two conditions are evaluated each run:
 *   1. contract_end_reminder — a project whose contract_end_date is within the next
 *      30 days (or already overdue) and which is not yet Closed. Reminds the team to
 *      consider an addendum.
 *   2. top_invoice_reminder  — a payment term whose estimated_date has arrived (today
 *      or earlier) but whose submit_invoice_date is still empty. Reminds the team to
 *      submit the invoice.
 *
 * Strategy: each run first deletes the existing reminder rows of these two types,
 * then regenerates fresh ones for every condition still active. This gives a single
 * row per (recipient, target) that re-surfaces as unread every day while the
 * condition holds, and disappears automatically once it is resolved.
 */
class SendProjectDeadlineReminders extends Command
{
    protected $signature = 'notifications:project-reminders';

    protected $description = 'Create daily contract-end and TOP invoice reminders for Head of Project & Project Admin.';

    /** Days before contract_end_date at which the reminder starts appearing. */
    private const CONTRACT_WINDOW_DAYS = 30;

    private const TYPE_CONTRACT = 'contract_end_reminder';
    private const TYPE_INVOICE  = 'top_invoice_reminder';

    public function handle(): int
    {
        $today = Carbon::today();

        // Recipients: all active Head of Project + Delivery Project Administrator.
        $recipientIds = Employee::whereIn('role_id', [
                RoleId::HEAD_OF_PROJECT->value,
                RoleId::DELIVERY_PROJECT_ADMIN->value,
            ])
            ->where('is_active', true)
            ->pluck('employee_id')
            ->all();

        if (empty($recipientIds)) {
            $this->warn('No active Head of Project / Project Admin recipients found. Nothing to do.');
            return self::SUCCESS;
        }

        // Wipe previous reminders of these types — they are live reminders, not history.
        Notification::whereIn('type', [self::TYPE_CONTRACT, self::TYPE_INVOICE])->delete();

        $contractCount = $this->buildContractReminders($recipientIds, $today);
        $invoiceCount  = $this->buildInvoiceReminders($recipientIds, $today);

        $this->info("Recipients: " . count($recipientIds)
            . " | Contract reminders: {$contractCount} | Invoice reminders: {$invoiceCount}");

        Log::info('Project deadline reminders generated', [
            'recipients'         => count($recipientIds),
            'contract_reminders' => $contractCount,
            'invoice_reminders'  => $invoiceCount,
        ]);

        return self::SUCCESS;
    }

    /**
     * Condition 1 — contract end date within 30 days or overdue (project not Closed).
     *
     * @param  array<int>  $recipientIds
     */
    private function buildContractReminders(array $recipientIds, Carbon $today): int
    {
        $created = 0;

        $projects = DeliveryProject::whereNotNull('contract_end_date')
            ->with('client.basicData')
            ->get();

        foreach ($projects as $project) {
            // Per decision: contract reminders skip Closed projects (no addendum needed).
            if (strcasecmp((string) $project->category, 'Closed') === 0) {
                continue;
            }

            $end = Carbon::parse($project->contract_end_date)->startOfDay();
            $daysUntil = (int) $today->diffInDays($end, false); // >0 upcoming, 0 today, <0 overdue

            // Notify only inside the 30-day window or once overdue.
            if ($daysUntil > self::CONTRACT_WINDOW_DAYS) {
                continue;
            }

            $io       = $project->io_number ?: ('#' . $project->id);
            $name     = $project->name ?: 'Untitled project';
            $customer = $project->client?->basicData?->name_1 ?: 'Unknown customer';
            $endStr   = $end->format('d M Y');

            if ($daysUntil > 0) {
                $preview = "[IO {$io}] {$name} — {$customer}. Contract ends in {$daysUntil} day(s) on {$endStr}. Review for a possible addendum.";
            } elseif ($daysUntil === 0) {
                $preview = "[IO {$io}] {$name} — {$customer}. Contract ends TODAY ({$endStr}). Review for a possible addendum.";
            } else {
                $overdue = abs($daysUntil);
                $preview = "[IO {$io}] {$name} — {$customer}. Contract OVERDUE by {$overdue} day(s) (ended {$endStr}). Addendum needed.";
            }

            $link = route('projects.show', $project->id, false);

            foreach ($recipientIds as $empId) {
                Notification::create([
                    'employee_id' => $empId,
                    'type'        => self::TYPE_CONTRACT,
                    'from_name'   => 'Contract Deadline',
                    'preview'     => $preview,
                    'link'        => $link,
                    'is_read'     => false,
                ]);
                $created++;
            }
        }

        return $created;
    }

    /**
     * Condition 2 — payment term estimated_date reached (today or past) and
     * submit_invoice_date still empty. Applies regardless of project status.
     *
     * @param  array<int>  $recipientIds
     */
    private function buildInvoiceReminders(array $recipientIds, Carbon $today): int
    {
        $created = 0;

        $terms = DeliveryProjectPaymentTerm::whereNull('submit_invoice_date')
            ->whereNotNull('estimated_date')
            ->whereDate('estimated_date', '<=', $today)
            ->with('project.client.basicData')
            ->get();

        foreach ($terms as $term) {
            $project = $term->project;
            if (!$project) {
                continue;
            }

            $io        = $project->io_number ?: ('#' . $project->id);
            $name      = $project->name ?: 'Untitled project';
            $customer  = $project->client?->basicData?->name_1 ?: 'Unknown customer';
            $termLabel = $term->payment_term ?: ('Term ' . $term->term_number);
            $estimated = Carbon::parse($term->estimated_date)->startOfDay();
            $estStr    = $estimated->format('d M Y');

            if ($estimated->lt($today)) {
                $preview = "[IO {$io}] {$name} — {$customer}. Invoice for \"{$termLabel}\" (Term #{$term->term_number}) OVERDUE since {$estStr}. Please fill the Submit Invoice Date.";
            } else {
                $preview = "[IO {$io}] {$name} — {$customer}. Invoice for \"{$termLabel}\" (Term #{$term->term_number}) due TODAY ({$estStr}). Please fill the Submit Invoice Date.";
            }

            // Deep-link to the Delivery Info section where the TOP Plan table lives.
            $link = route('projects.show', $project->id, false) . '#delivery';

            foreach ($recipientIds as $empId) {
                Notification::create([
                    'employee_id' => $empId,
                    'type'        => self::TYPE_INVOICE,
                    'from_name'   => 'Invoice Reminder',
                    'preview'     => $preview,
                    'link'        => $link,
                    'is_read'     => false,
                ]);
                $created++;
            }
        }

        return $created;
    }
}
