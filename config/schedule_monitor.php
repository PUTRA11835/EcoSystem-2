<?php

/**
 * Known scheduled tasks for the Control Center "Schedule Monitor" page.
 * Keys must match the `->name(...)` given to each Schedule::command() entry
 * in routes/console.php - that name is what Laravel's scheduling events
 * report back as $event->task->description, and it's what ties an execution
 * record back to one of these entries. A task that fires but isn't listed
 * here still gets recorded, it just won't show a frequency label or a
 * staleness check on the dashboard.
 */
return [
    'commands' => [
        // Not a real task - see App\Console\Commands\ScheduleHeartbeat. Tightest
        // threshold on the page: it runs every minute and does nothing, so
        // there is no legitimate reason for it to ever be more than a couple
        // minutes stale.
        'scheduler-heartbeat' => [
            'label'               => 'Scheduler Heartbeat',
            'command'             => 'schedule-monitor:heartbeat',
            'frequency_label'     => 'Every minute',
            'stale_after_minutes' => 3,
        ],
        'email-process-inbox' => [
            'label'               => 'Process Incoming Email',
            'command'             => 'email:process-inbox',
            'frequency_label'     => 'Every minute',
            'stale_after_minutes' => 5,
        ],
        'tickets-open-reminders' => [
            'label'               => 'Ticket Open Reminders',
            'command'             => 'tickets:open-reminders',
            'frequency_label'     => 'Every minute',
            'stale_after_minutes' => 5,
        ],
        'activities-recompute-status' => [
            'label'               => 'Recompute Delivery Activity Status',
            'command'             => 'activities:recompute-status',
            'frequency_label'     => 'Daily at 00:05',
            'stale_after_minutes' => 1560, // 26h grace
        ],
        'notifications-project-reminders' => [
            'label'               => 'Project Reminders (HoP/PA)',
            'command'             => 'notifications:project-reminders',
            'frequency_label'     => 'Daily at 07:00',
            'stale_after_minutes' => 1560,
        ],
        'onedrive-audit-links' => [
            'label'               => 'OneDrive Share Link Audit',
            'command'             => 'onedrive:audit-links --fix',
            'frequency_label'     => 'Daily at 02:30',
            'stale_after_minutes' => 1560,
        ],
        'ai-prune-conversations' => [
            'label'               => 'Prune Expired AI Conversations',
            'command'             => 'ai:prune-conversations --apply',
            'frequency_label'     => 'Twice daily (03:00, 15:00)',
            'stale_after_minutes' => 780, // 13h grace
        ],
        'security-detect-anomalies' => [
            'label'               => 'Security Anomaly Detection',
            'command'             => 'security:detect-anomalies',
            'frequency_label'     => 'Every 5 minutes',
            'stale_after_minutes' => 15,
        ],
        'security-prune-logs' => [
            'label'               => 'Prune Security/Audit Logs',
            'command'             => 'security:prune-logs --apply',
            'frequency_label'     => 'Daily at 04:00',
            'stale_after_minutes' => 1560, // 26h grace, same as other daily jobs
        ],
        'check-integration-health' => [
            'label'               => 'Integration Credential Health (MS Graph/AI)',
            'command'             => 'schedule-monitor:check-integration-health',
            'frequency_label'     => 'Every 6 hours',
            'stale_after_minutes' => 400, // ~6h40m grace
        ],
    ],

    // Admin alerting - reuses the same Notification/WebPush pipeline Security
    // Center already uses, so a broken/silent task shows up the same way a
    // security event does, without the admin needing to open Schedule Monitor.
    'alert_after_consecutive_failures' => 3,

    /**
     * Supervised queue workers (see docker/supervisord.conf) - a stuck/dead
     * worker never shows up in Failed Jobs, since a job that's never been
     * attempted can't have "failed" yet, it just sits in `jobs` piling up.
     * Only the queue names listed here are checked/displayed.
     *
     * 'reports' (Word Report Generator) is deliberately NOT listed - that
     * feature is on hold pending a leadership review, so its queue is left
     * unmonitored for now rather than surfacing health data for a feature
     * not yet signed off.
     */
    'queue_health' => [
        'default' => [
            'label'               => 'Default Queue (email, notifications, SLA events)',
            'max_pending'         => 200,
            'max_oldest_minutes'  => 30,
        ],
    ],

    /**
     * Server disk capacity - total/used/free plus a content breakdown, shown
     * on the Schedule Monitor page and alerted on the same threshold-crossing
     * pattern as everything else here (see ScheduleMonitorService::
     * checkDiskUsageAlerts()).
     *
     * 'check_path' resolves WHICH filesystem/mount to measure - disk_free_space()
     * reports stats for the mount point the given path lives on, not the path
     * itself, so base_path() correctly reflects the app's actual disk even if
     * storage/ is symlinked elsewhere.
     */
    'disk_usage' => [
        'check_path'       => base_path(),
        'warning_percent'  => 80,
        'critical_percent' => 90,

        // Directories worth breaking out individually - everything else falls
        // under the implicit "rest of check_path" the UI computes as
        // used_bytes minus the sum of these (plus the database row below).
        // Only actual data grows unbounded here; app code/vendor doesn't.
        'breakdown' => [
            'ticket_attachments'   => ['label' => 'Ticket Attachments',    'path' => storage_path('app/public/ticket-attachments')],
            'ticket_inline_images' => ['label' => 'Ticket Inline Images',  'path' => storage_path('app/public/ticket-inline-images')],
            'employee_attachments' => ['label' => 'Employee Attachments', 'path' => storage_path('app/public/employee_attachments')],
            'staging_attachments'  => ['label' => 'Staging Attachments',  'path' => storage_path('app/public/staging_attachments')],
            'database_backups'     => ['label' => 'Database Backups',     'path' => storage_path('app/private/backups')],
            'logs'                 => ['label' => 'Application Logs',     'path' => storage_path('logs')],
        ],
    ],
];
