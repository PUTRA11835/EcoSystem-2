<?php

namespace App\Services;

use App\Enums\RoleId;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Records the outcome of every scheduled task run (see
 * App\Providers\ScheduleMonitorServiceProvider for the event wiring) and
 * reads that data back for the Control Center "Schedule Monitor" page.
 */
class ScheduleMonitorService
{
    /** Max characters kept from an error message - full detail lives in the app log. */
    private const ERROR_TRUNCATE = 1000;

    public static function recordStarting(string $taskName): void
    {
        DB::table('scheduled_task_status')->updateOrInsert(
            ['task_name' => $taskName],
            [
                'last_started_at' => now(),
                'last_status'     => 'running',
                'updated_at'      => now(),
            ]
        );
    }

    /** Only call this for a confirmed exit code 0 - non-zero and null exit codes go through recordFailed()/recordSkipped(). */
    public static function recordFinished(string $taskName, float $runtimeSeconds): void
    {
        $row = DB::table('scheduled_task_status')->where('task_name', $taskName)->first();

        DB::table('scheduled_task_status')->updateOrInsert(
            ['task_name' => $taskName],
            [
                'last_finished_at'      => now(),
                'last_status'           => 'success',
                'last_exit_code'        => 0,
                'last_duration_ms'      => (int) round($runtimeSeconds * 1000),
                'last_error'            => null,
                'consecutive_failures'  => 0,
                // Task is healthy again - clear any staleness alert so the
                // NEXT time it goes quiet is treated as a fresh episode
                // worth notifying about, not silently suppressed forever.
                'stale_notified_at'     => null,
                'total_runs'            => ($row->total_runs ?? 0) + 1,
                'updated_at'            => now(),
            ]
        );
    }

    public static function recordFailed(string $taskName, ?Throwable $exception, ?int $exitCode, float $runtimeSeconds = 0.0): void
    {
        $row = DB::table('scheduled_task_status')->where('task_name', $taskName)->first();
        $consecutiveFailures = ($row->consecutive_failures ?? 0) + 1;
        $error = $exception ? mb_substr($exception->getMessage(), 0, self::ERROR_TRUNCATE) : null;

        DB::table('scheduled_task_status')->updateOrInsert(
            ['task_name' => $taskName],
            [
                'last_finished_at'     => now(),
                'last_status'          => 'failed',
                'last_exit_code'       => $exitCode,
                'last_duration_ms'     => (int) round($runtimeSeconds * 1000),
                'last_error'           => $error,
                'consecutive_failures' => $consecutiveFailures,
                'total_runs'           => ($row->total_runs ?? 0) + 1,
                'updated_at'           => now(),
            ]
        );

        DB::table('scheduled_task_runs')->insert([
            'task_name'   => $taskName,
            'status'      => 'failed',
            'started_at'  => $row->last_started_at ?? null,
            'finished_at' => now(),
            'duration_ms' => (int) round($runtimeSeconds * 1000),
            'exit_code'   => $exitCode,
            'error'       => $error,
            'created_at'  => now(),
        ]);

        // Fire exactly on the run that CROSSES the threshold, not >=, so a
        // task stuck failing every cycle notifies admins once per streak
        // rather than spamming one notification per subsequent failure -
        // same idempotency shape as LoginSecurityService::checkAccountThreshold().
        $threshold = (int) config('schedule_monitor.alert_after_consecutive_failures', 3);
        if ($threshold > 0 && $consecutiveFailures === $threshold) {
            $label = config("schedule_monitor.commands.{$taskName}.label", $taskName);
            self::notifyAdmins(
                "\"{$label}\" has failed {$consecutiveFailures} times in a row. Latest error: " . ($error ?: 'unknown'),
            );
        }
    }

    /** withoutOverlapping() prevented this run from ever starting (mutex held by a still-running previous instance), or a ->when()/->skip() filter excluded it. */
    public static function recordSkipped(string $taskName): void
    {
        $row = DB::table('scheduled_task_status')->where('task_name', $taskName)->first();

        DB::table('scheduled_task_status')->updateOrInsert(
            ['task_name' => $taskName],
            [
                'last_status' => 'skipped',
                'updated_at'  => now(),
            ]
        );

        DB::table('scheduled_task_runs')->insert([
            'task_name'   => $taskName,
            'status'      => 'skipped',
            'started_at'  => $row->last_started_at ?? null,
            'finished_at' => now(),
            'created_at'  => now(),
        ]);
    }

    /**
     * Every known command from config, merged with its live status row.
     * A command with no status row at all (never observed running, even
     * once) is surfaced as 'never_run' rather than silently omitted - that
     * is itself the most important thing this page needs to catch.
     */
    public static function getStatusData(): array
    {
        $known = config('schedule_monitor.commands', []);
        $rows = DB::table('scheduled_task_status')->get()->keyBy('task_name');

        $result = [];

        foreach ($known as $taskName => $meta) {
            $row = $rows->get($taskName);
            $result[] = self::buildStatusEntry($taskName, $meta, $row);
        }

        // Any task that actually ran but isn't in config (e.g. removed from
        // console.php, or a typo in ->name()) still shows up - an admin
        // should never lose visibility into something that is demonstrably
        // executing.
        foreach ($rows as $taskName => $row) {
            if (!isset($known[$taskName])) {
                $result[] = self::buildStatusEntry($taskName, null, $row);
            }
        }

        return $result;
    }

    /**
     * Scans every known task for staleness and notifies admins the first
     * time each one crosses its threshold - tracked via stale_notified_at
     * so a task that stays stale for hours doesn't re-notify on every call.
     * Meant to be driven by a periodic command (see routes/console.php);
     * deliberately NOT itself a monitored task (no ->name() on that
     * schedule entry) to avoid the self-referential case of alerting on
     * its own staleness.
     *
     * Caveat this does not cover: if the scheduler stops running entirely
     * (cron misconfigured), this check never fires either, since it is
     * itself a scheduled task. That failure mode has no fix from inside
     * Laravel - it needs an external uptime/heartbeat check.
     *
     * @return int number of new alerts sent
     */
    public static function checkStaleness(): int
    {
        $rows = DB::table('scheduled_task_status')->get()->keyBy('task_name');
        $sent = 0;

        foreach (config('schedule_monitor.commands', []) as $taskName => $meta) {
            $row = $rows->get($taskName);
            $entry = self::buildStatusEntry($taskName, $meta, $row);

            if (!$entry['is_stale']) {
                continue;
            }

            if ($row && $row->stale_notified_at) {
                continue; // already alerted for this ongoing episode
            }

            $lastActivity = $entry['last_finished_at'] ?? $entry['last_started_at'] ?? null;
            $preview = $lastActivity
                ? "\"{$entry['label']}\" has not completed since {$lastActivity} (expected: {$entry['frequency_label']})."
                : "\"{$entry['label']}\" has never been observed running (expected: {$entry['frequency_label']}).";

            self::notifyAdmins($preview);
            $sent++;

            DB::table('scheduled_task_status')->updateOrInsert(
                ['task_name' => $taskName],
                ['stale_notified_at' => now(), 'updated_at' => now()]
            );
        }

        return $sent;
    }

    /**
     * Live backlog snapshot for every supervised queue worker listed in
     * config('schedule_monitor.queue_health') - see docker/supervisord.conf
     * for the actual worker processes. A stuck/dead worker never produces a
     * Failed Jobs row (a job that's never been attempted can't have
     * "failed"), it just sits here piling up, which is exactly what this
     * reads: pending count and the age of the oldest pending job.
     */
    public static function getQueueHealth(): array
    {
        $result = [];

        foreach (config('schedule_monitor.queue_health', []) as $queueName => $meta) {
            $pendingCount = DB::table('jobs')->where('queue', $queueName)->count();
            // jobs.created_at is a raw Unix timestamp integer (Laravel's queue
            // worker convention), not a datetime string - must go through
            // createFromTimestamp() before Carbon diff math, a plain
            // now()->diffInMinutes($intTimestamp) does not parse it correctly.
            $oldestCreatedAtRaw = DB::table('jobs')->where('queue', $queueName)->min('created_at');
            // max(0, ...) + round(): a job queued a fraction of a second ago
            // otherwise diffs to a tiny negative float (sub-second clock
            // precision between the insert and this check), which reads
            // nonsensically as "-0.01 minutes old".
            $oldestPendingMinutes = $oldestCreatedAtRaw
                ? max(0, (int) round(now()->diffInMinutes(\Carbon\Carbon::createFromTimestamp($oldestCreatedAtRaw))))
                : 0;

            $isHealthy = $pendingCount <= ($meta['max_pending'] ?? PHP_INT_MAX)
                && $oldestPendingMinutes <= ($meta['max_oldest_minutes'] ?? PHP_INT_MAX);

            $result[] = [
                'queue_name'             => $queueName,
                'label'                  => $meta['label'] ?? $queueName,
                'pending_count'          => $pendingCount,
                'oldest_pending_minutes' => $oldestCreatedAtRaw ? $oldestPendingMinutes : null,
                'max_pending'            => $meta['max_pending'] ?? null,
                'max_oldest_minutes'     => $meta['max_oldest_minutes'] ?? null,
                'is_healthy'             => $isHealthy,
            ];
        }

        return $result;
    }

    /**
     * Periodic sweep counterpart to checkStaleness() - alerts admins once
     * per backlog episode per queue (Cache::add is atomic "alert only if not
     * already flagged"; the flag is cleared as soon as the queue is healthy
     * again so a future backlog re-alerts fresh, with a 24h TTL as a safety
     * net in case that clear step is ever missed).
     *
     * @return int number of new alerts sent
     */
    public static function checkQueueHealthAlerts(): int
    {
        $sent = 0;

        foreach (self::getQueueHealth() as $queue) {
            $cacheKey = "queue_health_alerted_{$queue['queue_name']}";

            if ($queue['is_healthy']) {
                Cache::forget($cacheKey);
                continue;
            }

            if (!Cache::add($cacheKey, true, now()->addHours(24))) {
                continue; // already alerted for this ongoing backlog
            }

            self::notifyAdmins(
                "\"{$queue['label']}\" queue has a backlog: {$queue['pending_count']} pending job(s)"
                . ($queue['oldest_pending_minutes'] !== null ? ", oldest is {$queue['oldest_pending_minutes']} minute(s) old" : '')
                . '. The worker may be stuck or offline.'
            );
            $sent++;
        }

        return $sent;
    }

    /**
     * Live total/used/free for the disk backing config('schedule_monitor.
     * disk_usage.check_path') - a single disk_free_space()/disk_total_space()
     * syscall pair, cheap enough to call on every page load and every alert
     * sweep with no caching needed (unlike getDiskBreakdown() below).
     */
    public static function getDiskSpace(): array
    {
        $checkPath = config('schedule_monitor.disk_usage.check_path', base_path());
        $total = @disk_total_space($checkPath);
        $free  = @disk_free_space($checkPath);

        if ($total === false || $free === false) {
            return [
                'check_path'   => $checkPath,
                'available'    => false,
                'total_bytes'  => null,
                'used_bytes'   => null,
                'free_bytes'   => null,
                'used_percent' => null,
                'status'       => 'unknown',
            ];
        }

        $used = (int) $total - (int) $free;
        $usedPercent = $total > 0 ? round(($used / $total) * 100, 1) : 0.0;

        $critical = (float) config('schedule_monitor.disk_usage.critical_percent', 90);
        $warning  = (float) config('schedule_monitor.disk_usage.warning_percent', 80);
        $status   = $usedPercent >= $critical ? 'critical' : ($usedPercent >= $warning ? 'warning' : 'ok');

        return [
            'check_path'   => $checkPath,
            'available'    => true,
            'total_bytes'  => (int) $total,
            'used_bytes'   => $used,
            'free_bytes'   => (int) $free,
            'used_percent' => $usedPercent,
            'status'       => $status,
        ];
    }

    /**
     * Content breakdown for config('schedule_monitor.disk_usage.breakdown')
     * plus the database's own data+index size. Recursive directory walks are
     * not cheap on a folder with years of ticket attachments, so this is
     * cached - the Schedule Monitor page auto-refreshes every 30s and this
     * data doesn't meaningfully change that often.
     */
    public static function getDiskBreakdown(): array
    {
        return Cache::remember('schedule_monitor_disk_breakdown', now()->addMinutes(30), function () {
            $items = [];
            $storageItemizedTotal = 0;

            foreach (config('schedule_monitor.disk_usage.breakdown', []) as $key => $meta) {
                $path   = $meta['path'] ?? null;
                $exists = $path && is_dir($path);
                $size   = $exists ? self::directorySize($path) : 0;

                $items[] = [
                    'key'        => $key,
                    'label'      => $meta['label'] ?? $key,
                    'size_bytes' => $size,
                    'exists'     => $exists,
                ];

                $storageItemizedTotal += $size;
            }

            $connection = config('database.default');
            $database   = config("database.connections.{$connection}.database");

            $items[] = [
                'key'        => 'database',
                'label'      => "Database ({$database})",
                'size_bytes' => self::databaseSize($database),
                'exists'     => true,
            ];

            // Everything else under storage/ not individually itemized above -
            // framework cache/sessions/compiled views, htmlpurifier cache, the
            // legacy storage/app/backups/ path from the old manual-Navicat
            // era, etc. Real and worth seeing, just not important enough to
            // name one by one in config.
            $storagePath  = storage_path();
            $storageTotal = is_dir($storagePath) ? self::directorySize($storagePath) : 0;

            $items[] = [
                'key'        => 'storage_other',
                'label'      => 'Other Storage (cache, sessions, compiled views, misc)',
                'size_bytes' => max(0, $storageTotal - $storageItemizedTotal),
                'exists'     => true,
            ];

            // This app's own code footprint - vendor, node_modules, .git,
            // source - everything under the app root EXCEPT storage/, which
            // is entirely accounted for above. Answers "how big is EcoSystem
            // itself" as its own line, separate from the data it holds -
            // this is what "Other" on the page used to silently swallow.
            $appPath  = base_path();
            $appTotal = is_dir($appPath) ? self::directorySize($appPath) : 0;

            $items[] = [
                'key'        => 'application_code',
                'label'      => 'Application Code (vendor, git, source)',
                'size_bytes' => max(0, $appTotal - $storageTotal),
                'exists'     => true,
            ];

            usort($items, fn ($a, $b) => $b['size_bytes'] <=> $a['size_bytes']);

            return $items;
        });
    }

    /** Sums file sizes recursively; an unreadable subdirectory (permissions) reports what was summed so far rather than failing the whole breakdown. */
    private static function directorySize(string $path): int
    {
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY,
                \RecursiveIteratorIterator::CATCH_GET_CHILD
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (Throwable $e) {
            // Best-effort - partial total beats no total.
        }

        return $size;
    }

    private static function databaseSize(?string $database): int
    {
        if (!$database) {
            return 0;
        }

        $row = DB::selectOne(
            'SELECT SUM(data_length + index_length) AS bytes FROM information_schema.tables WHERE table_schema = ?',
            [$database]
        );

        return (int) ($row->bytes ?? 0);
    }

    /**
     * Periodic sweep counterpart to checkStaleness()/checkQueueHealthAlerts()
     * - alerts once per severity per episode (Cache::add is atomic "alert only
     * if not already flagged"). 'warning' and 'critical' use separate keys so
     * an escalation from warning to critical still gets its own fresh alert
     * even though warning already fired for this episode.
     *
     * @return int number of new alerts sent
     */
    public static function checkDiskUsageAlerts(): int
    {
        $disk = self::getDiskSpace();

        if (!$disk['available']) {
            return 0;
        }

        if ($disk['status'] === 'ok') {
            Cache::forget('schedule_monitor_disk_alerted_warning');
            Cache::forget('schedule_monitor_disk_alerted_critical');
            return 0;
        }

        $cacheKey = "schedule_monitor_disk_alerted_{$disk['status']}";
        if (!Cache::add($cacheKey, true, now()->addHours(24))) {
            return 0; // already alerted for this ongoing episode at this severity
        }

        self::notifyAdmins(sprintf(
            'Disk usage on %s is at %s%% (%s used of %s) - status: %s.',
            $disk['check_path'],
            $disk['used_percent'],
            self::formatBytes($disk['used_bytes']),
            self::formatBytes($disk['total_bytes']),
            strtoupper($disk['status'])
        ));

        return 1;
    }

    private static function formatBytes(?int $bytes): string
    {
        if ($bytes === null) {
            return 'unknown';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $value = (float) $bytes;
        $i = 0;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1) . ' ' . $units[$i];
    }

    /** Same shape as LoginSecurityService::notifyAdmins() - every EC Administrator gets an in-app + push notification. */
    private static function notifyAdmins(string $preview): void
    {
        $adminIds = DB::table('employee_role_assignment as era')
            ->join('employee_role as er', 'era.role_id', '=', 'er.id')
            ->where('er.id', RoleId::EC_ADMINISTRATOR->value)
            ->pluck('era.employee_id');

        foreach ($adminIds as $adminId) {
            Notification::create([
                'employee_id' => $adminId,
                'type'        => 'schedule_alert',
                'from_name'   => 'Schedule Monitor',
                'preview'     => $preview,
                'link'        => route('admin.schedule-monitor'),
                'is_read'     => false,
            ]);
        }
    }

    private static function buildStatusEntry(string $taskName, ?array $meta, $row): array
    {
        $lastActivity = $row->last_finished_at ?? $row->last_started_at ?? null;
        $staleAfter = $meta['stale_after_minutes'] ?? null;

        $isStale = !$row || !$lastActivity
            ? true
            : ($staleAfter !== null && now()->diffInMinutes($lastActivity) > $staleAfter);

        return [
            'task_name'             => $taskName,
            'label'                 => $meta['label'] ?? $taskName,
            'command'               => $meta['command'] ?? null,
            'frequency_label'       => $meta['frequency_label'] ?? 'Unknown',
            'status'                => $row->last_status ?? 'never_run',
            // WIB-formatted for display, matching getRuns()'s "d M Y H:i" + "
            // WIB" convention below - previously returned the raw DB
            // datetime string, which read inconsistently next to the
            // formatted Recent Failures & Skips table on the same page.
            'last_started_at'       => self::formatWib($row->last_started_at ?? null),
            'last_finished_at'      => self::formatWib($row->last_finished_at ?? null),
            'last_duration_ms'      => $row->last_duration_ms ?? null,
            'last_exit_code'        => $row->last_exit_code ?? null,
            'last_error'            => $row->last_error ?? null,
            'consecutive_failures'  => $row->consecutive_failures ?? 0,
            'total_runs'            => $row->total_runs ?? 0,
            'is_stale'              => $isStale,
        ];
    }

    private static function formatWib(?string $datetime): ?string
    {
        if (!$datetime) {
            return null;
        }

        return \Carbon\Carbon::parse($datetime)->setTimezone('Asia/Jakarta')->format('d M Y H:i') . ' WIB';
    }
}
