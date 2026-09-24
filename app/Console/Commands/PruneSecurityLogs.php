<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\SecurityEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Prunes the three tables Security Center writes to unbounded:
 * `audit_logs`, `security_events`, `login_activity`. None of them has an
 * existing retention mechanism (the only precedent in this codebase is
 * `ai:prune-conversations`, which prunes an unrelated table).
 *
 * Dry-run by default (prints what WOULD be deleted); pass --apply to
 * actually delete, same convention as ai:prune-conversations and
 * tickets:cleanup-ndr. Retention windows are config-driven
 * (config('security_center.retention'), env-overridable) - a value below 1
 * disables pruning for that table entirely.
 *
 * Unlike ai:prune-conversations (which deletes per-model via ->each->delete()
 * so any model cascade/events fire), this command deletes via a plain
 * chunked whereIn(...)->delete(): none of these 3 tables have FK constraints
 * or delete-hooks (confirmed - audit_logs/security_events migrations
 * explicitly note "intentionally no FK constraint" for write-volume reasons),
 * so there's nothing a per-model delete would trigger that a bulk delete
 * wouldn't, and bulk delete is meaningfully faster at these row counts.
 */
class PruneSecurityLogs extends Command
{
    protected $signature = 'security:prune-logs {--apply : Actually delete rows (default is a dry-run preview)}';

    protected $description = 'Prune audit_logs, security_events, and login_activity past their configured retention windows.';

    private const CHUNK_SIZE = 200;

    public function handle(): int
    {
        $apply = (bool) $this->option('apply');

        $results = [
            $this->pruneAuditLogs($apply),
            $this->pruneSecurityEvents($apply),
            $this->pruneLoginActivity($apply),
        ];

        $this->table(
            ['Table', 'Retention (days)', 'Applied?', $apply ? 'Deleted' : 'Would delete'],
            array_map(fn ($r) => [$r['table'], $r['retention_days'], $apply ? 'yes' : 'no', $r['count']], $results)
        );

        if (!$apply) {
            $this->warn('Dry-run: no rows deleted. Re-run with --apply to actually delete.');
        }

        Log::info('PruneSecurityLogs completed', [
            'applied' => $apply,
            'results' => $results,
        ]);

        return self::SUCCESS;
    }

    private function pruneAuditLogs(bool $apply): array
    {
        $days = (int) config('security_center.retention.audit_logs.retention_days', 365);

        if ($days < 1) {
            return ['table' => 'audit_logs', 'retention_days' => 'keep forever', 'count' => 0];
        }

        $query = AuditLog::where('created_at', '<', now()->subDays($days));

        return [
            'table'          => 'audit_logs',
            'retention_days' => $days,
            'count'          => $this->countAndMaybeDelete($query, AuditLog::class, 'id', $apply),
        ];
    }

    private function pruneSecurityEvents(bool $apply): array
    {
        $cfg  = config('security_center.retention.security_events', []);
        $days = (int) ($cfg['retention_days'] ?? 180);

        if ($days < 1) {
            return ['table' => 'security_events', 'retention_days' => 'keep forever', 'count' => 0];
        }

        $query = SecurityEvent::where('created_at', '<', now()->subDays($days));

        if ($cfg['skip_unresolved'] ?? true) {
            $query->where('status', 'resolved');
        }

        return [
            'table'          => 'security_events',
            'retention_days' => $days,
            'count'          => $this->countAndMaybeDelete($query, SecurityEvent::class, 'id', $apply),
        ];
    }

    private function pruneLoginActivity(bool $apply): array
    {
        $days = (int) config('security_center.retention.login_activity.retention_days', 180);

        if ($days < 1) {
            return ['table' => 'login_activity', 'retention_days' => 'keep forever', 'count' => 0];
        }

        // Filtered on login_at, not created_at - login_at is the indexed
        // column on this table (created_at has no index), so this avoids a
        // full table scan on what's likely the largest of the 3 tables.
        $query = LoginActivity::where('login_at', '<', now()->subDays($days));

        return [
            'table'          => 'login_activity',
            'retention_days' => $days,
            'count'          => $this->countAndMaybeDelete($query, LoginActivity::class, 'activity_id', $apply),
        ];
    }

    private function countAndMaybeDelete($query, string $modelClass, string $keyName, bool $apply): int
    {
        $count = (clone $query)->count();

        if (!$apply || $count === 0) {
            return $count;
        }

        $query->chunkById(self::CHUNK_SIZE, function ($rows) use ($modelClass, $keyName) {
            $modelClass::whereIn($keyName, $rows->pluck($keyName))->delete();
        }, $keyName, $keyName);

        return $count;
    }
}
