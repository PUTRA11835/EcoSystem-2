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
];
