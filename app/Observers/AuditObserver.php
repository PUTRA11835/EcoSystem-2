<?php

namespace App\Observers;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Shared observer for every model using the App\Traits\Auditable trait.
 * Writes one row to audit_logs per create/update/delete — fire-and-forget,
 * same pattern as AuthController::recordActivity(): a logging failure must
 * never break the actual business operation.
 */
class AuditObserver
{
    private const GLOBAL_DENYLIST_PATTERNS = ['password', 'token', 'secret'];

    private const IGNORED_ATTRIBUTES = ['updated_at', 'created_at'];

    public function created(Model $model): void
    {
        $attributes = $this->stripIgnored($model->getAttributes());

        $this->log($model, 'created', null, $this->redact($attributes, $model->auditExcludedAttributes()));
    }

    public function updated(Model $model): void
    {
        $changes = $this->stripIgnored($model->getChanges());

        if (empty($changes)) {
            return; // no meaningful change (e.g. a bare touch())
        }

        $excluded = $model->auditExcludedAttributes();
        $original = array_intersect_key($model->getOriginal(), $changes);

        $this->log($model, 'updated', $this->redact($original, $excluded), $this->redact($changes, $excluded));
    }

    public function deleted(Model $model): void
    {
        $attributes = $this->stripIgnored($model->getAttributes());

        $this->log($model, 'deleted', $this->redact($attributes, $model->auditExcludedAttributes()), null);
    }

    public function forceDeleted(Model $model): void
    {
        $this->deleted($model);
    }

    private function log(Model $model, string $event, ?array $old, ?array $new): void
    {
        try {
            [$actorId, $actorRoleId, $actorName] = $this->actorContext();

            AuditLog::create([
                'auditable_type' => class_basename($model),
                'auditable_id'   => $model->getKey(),
                'module'         => $model->auditModuleLabel(),
                'event'          => $event,
                'record_label'   => $model->auditRecordLabel(),
                'description'    => $this->buildDescription($model, $event, $new),
                'actor_id'       => $actorId,
                'actor_role_id'  => $actorRoleId,
                'actor_name'     => $actorName,
                'old_values'     => $old,
                'new_values'     => $new,
                'ip_address'     => request()?->ip(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('AuditObserver failed to write audit log', [
                'error' => $e->getMessage(),
                'model' => get_class($model),
                'event' => $event,
            ]);
        }
    }

    /**
     * Human-readable one-liner, e.g. "marked Customer Mandays as Approved
     * — Ticket #1234" or "added Employee: Budi Santoso". Shown next to the
     * Actor column so the row reads as a sentence (Actor + description).
     */
    private function buildDescription(Model $model, string $event, ?array $new): string
    {
        // Deliberately the specific record type (e.g. "Delivery Project Risk"),
        // not the coarse module/domain grouping — using the domain label here
        // would misleadingly read as if the whole Delivery Project changed.
        $subject = $this->humanizeClassName($model);
        $suffix  = $this->subjectSuffix($model);

        if ($event === 'created') {
            return "added {$subject}{$suffix}";
        }

        if ($event === 'deleted') {
            return "deleted {$subject}{$suffix}";
        }

        $statusKey = $this->statusLikeKey($new ?? []);
        if ($statusKey !== null) {
            $qualifier = $statusKey === 'status' ? '' : ' ' . $this->humanizeFieldName($statusKey);
            return "marked {$subject}{$qualifier} as " . $this->humanizeStatus($new[$statusKey]) . $suffix;
        }

        return "updated {$subject}{$suffix}";
    }

    /**
     * Finds a status-like field among the changed attributes — the literal
     * "status" column if present (most models), else the first "*_status"
     * field (e.g. Ticket.mandays_proposal_status, TicketSla.response_status).
     */
    private function statusLikeKey(array $new): ?string
    {
        if (array_key_exists('status', $new) && is_string($new['status'])) {
            return 'status';
        }

        foreach ($new as $key => $value) {
            if (is_string($value) && str_ends_with($key, '_status')) {
                return $key;
            }
        }

        return null;
    }

    /** "response_status" -> "Response". */
    private function humanizeFieldName(string $key): string
    {
        return ucwords(str_replace('_', ' ', preg_replace('/_status$/', '', $key)));
    }

    /** "DeliveryProjectRisk" -> "Delivery Project Risk". */
    private function humanizeClassName(Model $model): string
    {
        return trim(preg_replace('/(?<!^)[A-Z]/', ' $0', class_basename($model)));
    }

    /** " — Ticket #1234" when the record references a ticket, else ": {label}". */
    private function subjectSuffix(Model $model): string
    {
        $ticketId = $model->getAttribute('ticket_id');

        if ($ticketId) {
            return ' — Ticket #' . $ticketId;
        }

        return ': ' . $model->auditRecordLabel();
    }

    private function humanizeStatus(string $status): string
    {
        return ucwords(str_replace(['_', '-'], ' ', $status));
    }

    private function actorContext(): array
    {
        try {
            $user = session('user');
        } catch (\Throwable $e) {
            $user = null;
        }

        if (!is_array($user) || ($user['type'] ?? null) !== 'employee') {
            return [null, null, null];
        }

        return [
            $user['id'] ?? null,
            $user['role']['id'] ?? null,
            $user['name'] ?? null,
        ];
    }

    private function stripIgnored(array $attributes): array
    {
        foreach (self::IGNORED_ATTRIBUTES as $key) {
            unset($attributes[$key]);
        }

        return $attributes;
    }

    private function redact(array $attributes, array $excluded): array
    {
        $excludedLower = array_map('strtolower', $excluded);

        foreach ($attributes as $key => $value) {
            $keyLower = strtolower($key);

            if (in_array($keyLower, $excludedLower, true)) {
                $attributes[$key] = '***redacted***';
                continue;
            }

            foreach (self::GLOBAL_DENYLIST_PATTERNS as $pattern) {
                if (str_contains($keyLower, $pattern)) {
                    $attributes[$key] = '***redacted***';
                    break;
                }
            }
        }

        return $attributes;
    }
}
