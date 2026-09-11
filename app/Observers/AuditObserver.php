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

    /** Beyond this many changed fields, the inline summary switches from "Field: old → new" to a bare field-name list. */
    private const MAX_INLINE_CHANGE_FIELDS = 3;

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
                'description'    => $this->buildDescription($model, $event, $old, $new),
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
     * (Chat Notes: empty → Called customer) — Ticket #1234" or "added
     * Employee: Budi Santoso". Shown next to the Actor column so the row
     * reads as a sentence (Actor + description).
     */
    private function buildDescription(Model $model, string $event, ?array $old, ?array $new): string
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

        $old ??= [];
        $new ??= [];

        $statusKey = $this->statusLikeKey($new);
        if ($statusKey !== null) {
            $qualifier = $statusKey === 'status' ? '' : ' ' . $this->humanizeFieldName($statusKey);
            $rest = $this->summarizeChanges(
                array_diff_key($old, [$statusKey => true]),
                array_diff_key($new, [$statusKey => true])
            );
            $restText = $rest !== '' ? " ({$rest})" : '';

            return "marked {$subject}{$qualifier} as " . $this->humanizeStatus($new[$statusKey]) . $restText . $suffix;
        }

        $summary = $this->summarizeChanges($old, $new);
        $summaryText = $summary !== '' ? " ({$summary})" : '';

        return "updated {$subject}{$summaryText}{$suffix}";
    }

    /**
     * Short human summary of what changed, e.g. "Priority: Low → High" for
     * one or two fields, or a bare field-name list once there are more than
     * MAX_INLINE_CHANGE_FIELDS — the full before/after values for every
     * field are still available via the Changes modal (old_values/
     * new_values), this is just enough to read the row at a glance without
     * opening it.
     */
    private function summarizeChanges(array $old, array $new): string
    {
        $keys = array_keys($new);

        if (empty($keys)) {
            return '';
        }

        if (count($keys) <= self::MAX_INLINE_CHANGE_FIELDS) {
            $parts = array_map(
                fn ($key) => $this->humanizeFieldName($key) . ': '
                    . $this->formatChangeValue($old[$key] ?? null) . ' → ' . $this->formatChangeValue($new[$key]),
                $keys
            );

            return implode(', ', $parts);
        }

        $shown = array_slice($keys, 0, self::MAX_INLINE_CHANGE_FIELDS);
        $labels = array_map(fn ($key) => $this->humanizeFieldName($key), $shown);
        $remaining = count($keys) - count($shown);

        return implode(', ', $labels) . " +{$remaining} more";
    }

    /** Trims a raw attribute value down to something that reads well inline in a sentence. */
    private function formatChangeValue(mixed $value): string
    {
        if ($value === null || $value === '') {
            return 'empty';
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value)) {
            $value = json_encode($value);
        }

        $value = (string) $value;

        return mb_strlen($value) > 40 ? mb_substr($value, 0, 40) . '…' : $value;
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

    /** " — Ticket #26080012" when the record references a ticket, else ": {label}". */
    private function subjectSuffix(Model $model): string
    {
        $ticketId = $model->getAttribute('ticket_id');

        if ($ticketId) {
            return ' — Ticket #' . $this->resolveTicketNumber($model, $ticketId);
        }

        return ': ' . $model->auditRecordLabel();
    }

    /**
     * ticket_id is the internal auto-increment PK — it's never shown
     * anywhere in the product. Every screen (ticket list, ticket detail,
     * notifications) identifies a ticket by its human-facing ticket_number
     * (e.g. "26080012", see TicketNumberService), so the audit trail has to
     * resolve to the same value or admins can't cross-reference a row here
     * against what they see on screen.
     */
    private function resolveTicketNumber(Model $model, int|string $ticketId): string
    {
        if ($model instanceof \App\Models\Ticket) {
            return (string) ($model->getAttribute('ticket_number') ?? $ticketId);
        }

        $ticketNumber = \App\Models\Ticket::where('ticket_id', $ticketId)->value('ticket_number');

        return (string) ($ticketNumber ?? $ticketId);
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
