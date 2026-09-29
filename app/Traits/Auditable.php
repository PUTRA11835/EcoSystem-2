<?php

namespace App\Traits;

use App\Observers\AuditObserver;

trait Auditable
{
    public static function bootAuditable(): void
    {
        static::observe(AuditObserver::class);
    }

    /** Domain label shown in the audit log's Module column/filter. */
    public function auditModuleLabel(): string
    {
        return static::$auditModule ?? class_basename($this);
    }

    /** Human-readable snapshot of this record for the audit log list. */
    public function auditRecordLabel(): string
    {
        foreach ([
            'full_name', 'name', 'subject', 'title', 'username', 'eci',
            'bank_name', 'group_name', 'description', 'action', 'activity', 'code',
        ] as $attr) {
            if (!empty($this->{$attr})) {
                return (string) $this->{$attr};
            }
        }

        return class_basename($this) . ' #' . $this->getKey();
    }

    /** Attribute names to redact from old/new value snapshots. */
    public function auditExcludedAttributes(): array
    {
        return array_merge($this->hidden ?? [], static::$auditExcept ?? []);
    }

    /**
     * Attribute names left out of the audit trail entirely (not even
     * redacted) - for system-touched bookkeeping columns that change on
     * nearly every request but carry no audit value (e.g. a "last activity"
     * cache column bumped on every incoming message). Unlike
     * auditExcludedAttributes(), a change to one of these never appears in
     * the change summary and, if it's the only thing that changed, no audit
     * row is written at all.
     */
    public function auditIgnoredAttributes(): array
    {
        return static::$auditIgnore ?? [];
    }
}
