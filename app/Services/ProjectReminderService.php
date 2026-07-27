<?php

namespace App\Services;

use App\Models\DeliveryProject;
use App\Models\DeliveryProjectPaymentTerm;
use App\Models\Employee;
use App\Models\Notification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Single source of truth for the project-deadline bell reminders.
 *
 * Two live reminder types are evaluated together:
 *   1. contract_end_reminder — project whose contract_end_date is within the next
 *      CONTRACT_WINDOW_DAYS (or already overdue) and which is not yet Closed.
 *   2. top_invoice_reminder  — payment term whose estimated_date has arrived (today
 *      or earlier) while submit_invoice_date is still empty.
 *
 * Strategy: {@see syncAll()} deletes every existing reminder of these two types and
 * regenerates fresh ones for every still-active condition. Because it is fully
 * idempotent it can be called from:
 *   - the daily scheduler ({@see \App\Console\Commands\SendProjectDeadlineReminders}); and
 *   - any controller that mutates a contract date or a TOP payment term, so the bell
 *     reflects the change immediately instead of waiting for the next 07:00 run.
 */
class ProjectReminderService
{
    /** Days before contract_end_date at which the reminder starts appearing. */
    private const CONTRACT_WINDOW_DAYS = 30;

    /**
     * Roles that receive project-deadline reminders, matched by the stable
     * `employee_role.name` (not by ID — see syncAll() for why).
     */
    private const RECIPIENT_ROLE_NAMES = [
        'Delivery Project Head',
        'Delivery Project Administrator',
    ];

    private const TYPE_CONTRACT = 'contract_end_reminder';
    private const TYPE_INVOICE  = 'top_invoice_reminder';

    /**
     * Rebuild every contract-end & TOP invoice reminder from the current data.
     *
     * @return array{recipients:int, contract:int, invoice:int}
     */
    public function syncAll(): array
    {
        $today = Carbon::today();

        // Recipients: all active Head of Project + Delivery Project Administrator.
        //
        // Resolve by role NAME, not by fixed enum ID: role IDs diverge between
        // environments (migrate:fresh assigns different IDs to the extended roles,
        // e.g. "Delivery Project Administrator" is id 62 here but the enum hardcodes
        // 12 — which actually maps to "Delivery RPMO User" in this DB). Matching on the
        // stable role name guarantees only the intended roles ever get notified and
        // avoids silently leaking reminders to an unrelated role.
        $recipientIds = Employee::whereHas('roles', fn($q) =>
                $q->whereIn('employee_role.name', self::RECIPIENT_ROLE_NAMES))
            ->where('is_active', true)
            ->pluck('employee_id')
            ->all();

        // Wipe previous reminders of these types — they are live reminders, not history.
        // Done even when there are no recipients so stale rows never linger.
        Notification::whereIn('type', [self::TYPE_CONTRACT, self::TYPE_INVOICE])->delete();

        // Peta nama lengkap → employee_id (aktif) untuk me-resolve Account Executive
        // internal sebuah project. Sales (AE) hanya dapat reminder untuk project-nya
        // sendiri; Head/Admin di atas tetap dapat semua project. Tidak early-return
        // meski Head/Admin kosong agar AE tetap dapat notifikasi project-nya.
        $aeNameMap = $this->activeEmployeeNameMap();

        $contract = $this->buildContractReminders($recipientIds, $today, $aeNameMap);
        $invoice  = $this->buildInvoiceReminders($recipientIds, $today, $aeNameMap);

        Log::info('Project deadline reminders synced', [
            'recipients' => count($recipientIds),
            'contract'   => $contract,
            'invoice'    => $invoice,
        ]);

        return ['recipients' => count($recipientIds), 'contract' => $contract, 'invoice' => $invoice];
    }

    /**
     * Convenience wrapper for controllers: never let a reminder-sync failure bubble up
     * and break the user's save. Logs and swallows any error.
     */
    public function syncAllQuietly(): void
    {
        try {
            $this->syncAll();
        } catch (\Throwable $e) {
            Log::warning('Project reminder sync failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Condition 1 — contract end date within the window or overdue (project not Closed).
     *
     * @param  array<int>            $recipientIds  global Head/Admin recipients
     * @param  array<string,array>   $aeNameMap     lowercased full name → [employee_id]
     */
    private function buildContractReminders(array $recipientIds, Carbon $today, array $aeNameMap = []): int
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

            // Notify only inside the window or once overdue.
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

            // Global Head/Admin + AE internal project ini (jika ada), deduped.
            $recipients = $this->recipientsForProject($recipientIds, $project, $aeNameMap);

            foreach ($recipients as $empId) {
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
     * @param  array<int>            $recipientIds  global Head/Admin recipients
     * @param  array<string,array>   $aeNameMap     lowercased full name → [employee_id]
     */
    private function buildInvoiceReminders(array $recipientIds, Carbon $today, array $aeNameMap = []): int
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

            // Global Head/Admin + AE internal project ini (jika ada), deduped.
            $recipients = $this->recipientsForProject($recipientIds, $project, $aeNameMap);

            foreach ($recipients as $empId) {
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

    /**
     * Recipient list for a single project's reminders: the global Head/Admin list
     * plus the project's own internal Account Executive (if resolvable & active),
     * deduplicated. This is how "sales" get reminders scoped to their own projects.
     *
     * @param  array<int>          $globalIds
     * @param  array<string,array> $aeNameMap
     * @return array<int>
     */
    private function recipientsForProject(array $globalIds, DeliveryProject $project, array $aeNameMap): array
    {
        return array_values(array_unique(array_merge(
            $globalIds,
            $this->aeRecipientIds($project, $aeNameMap)
        )));
    }

    /**
     * Resolve the internal AE of a project to employee id(s). Only "Internal" AEs
     * map to an employee (External AEs are free-text outsiders). Matched by the
     * stored ae_name against the active-employee full-name map.
     *
     * @param  array<string,array> $aeNameMap
     * @return array<int>
     */
    private function aeRecipientIds(DeliveryProject $project, array $aeNameMap): array
    {
        if (strcasecmp((string) $project->ae_type, 'Internal') !== 0) {
            return [];
        }
        $name = strtolower(trim((string) $project->ae_name));
        if ($name === '') {
            return [];
        }
        return $aeNameMap[$name] ?? [];
    }

    /**
     * Map of active employees keyed by lowercased full name → [employee_id].
     * Full name mirrors EmployeeBasicData::getFullNameAttribute()
     * (trim(first_name . ' ' . last_name)) so it lines up with the ae_name stored
     * from the project form's employee dropdown.
     *
     * @return array<string,array<int>>
     */
    private function activeEmployeeNameMap(): array
    {
        return DB::table('employee')
            ->join('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
            ->where('employee.is_active', true)
            ->select(
                'employee.employee_id',
                DB::raw("LOWER(TRIM(CONCAT(COALESCE(employee_basic_data.first_name,''),' ',COALESCE(employee_basic_data.last_name,'')))) as full_name")
            )
            ->get()
            ->filter(fn($r) => $r->full_name !== '')
            ->groupBy('full_name')
            ->map(fn($g) => $g->pluck('employee_id')->map(fn($id) => (int) $id)->all())
            ->all();
    }
}
