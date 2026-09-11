<?php

namespace App\Console\Commands;

use App\Services\ProjectReminderService;
use Illuminate\Console\Command;

/**
 * Daily reminders for the Head of Project & Delivery Project Administrator roles.
 *
 * The actual evaluation lives in {@see ProjectReminderService::syncAll()} so the very
 * same logic can be triggered on-demand whenever a contract date or a TOP payment term
 * is changed (see DeliveryProjectController / DeliveryProjectPaymentTermController).
 * This command is just the daily heartbeat that re-surfaces still-active reminders.
 */
class SendProjectDeadlineReminders extends Command
{
    protected $signature = 'notifications:project-reminders';

    protected $description = 'Create daily contract-end and TOP invoice reminders for Head of Project & Project Admin.';

    public function handle(ProjectReminderService $reminders): int
    {
        $result = $reminders->syncAll();

        $this->info("Recipients: {$result['recipients']}"
            . " | Contract reminders: {$result['contract']}"
            . " | Invoice reminders: {$result['invoice']}");

        return self::SUCCESS;
    }
}
