<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AuditLog extends Model
{
    public $timestamps = false; // only created_at (set via useCurrent)

    protected $fillable = [
        'auditable_type',
        'auditable_id',
        'module',
        'event',
        'record_label',
        'description',
        'actor_id',
        'actor_role_id',
        'actor_name',
        'old_values',
        'new_values',
        'ip_address',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function actor()
    {
        return $this->belongsTo(Employee::class, 'actor_id', 'employee_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Human-readable event label */
    public function getEventLabel(): string
    {
        return match ($this->event) {
            'created' => 'Created',
            'updated' => 'Updated',
            'deleted' => 'Deleted',
            default   => ucfirst($this->event),
        };
    }

    /** Badge color class for event type */
    public function getEventColor(): string
    {
        return match ($this->event) {
            'created' => 'bg-green-100 text-green-700',
            'updated' => 'bg-blue-100 text-blue-700',
            'deleted' => 'bg-red-100 text-red-700',
            default   => 'bg-gray-100 text-gray-600',
        };
    }

    /**
     * Fire-and-forget write for callers outside the AuditObserver/Auditable
     * lifecycle (e.g. AI prompt logging below). Same contract as
     * AuditObserver::log(): a logging failure must never break the caller.
     */
    public static function record(array $attributes): void
    {
        try {
            static::create($attributes);
        } catch (\Throwable $e) {
            Log::warning('AuditLog::record failed', [
                'error'          => $e->getMessage(),
                'auditable_type' => $attributes['auditable_type'] ?? null,
            ]);
        }
    }

    /**
     * Fire-and-forget write for a controller action that mutates data via
     * raw DB::table() query builder calls instead of the Eloquent model —
     * so Eloquent lifecycle events (and therefore AuditObserver) never
     * fire, even when the underlying model is Auditable. Resolves the
     * actor from the session the same way AuditObserver does, so these
     * rows read identically to observer-written ones.
     */
    public static function recordAction(
        string $module,
        string $auditableType,
        int|string $auditableId,
        string $event,
        ?string $recordLabel,
        string $description,
        ?array $old,
        ?array $new
    ): void {
        [$actorId, $actorRoleId, $actorName] = static::currentActor();

        static::record([
            'auditable_type' => $auditableType,
            'auditable_id'   => $auditableId,
            'module'         => $module,
            'event'          => $event,
            'record_label'   => $recordLabel,
            'description'    => $description,
            'actor_id'       => $actorId,
            'actor_role_id'  => $actorRoleId,
            'actor_name'     => $actorName,
            'old_values'     => $old,
            'new_values'     => $new,
            'ip_address'     => request()?->ip(),
        ]);
    }

    /** Same session shape AuditObserver::actorContext() reads — [actorId, actorRoleId, actorName]. */
    private static function currentActor(): array
    {
        try {
            $user = session('user');
        } catch (\Throwable $e) {
            $user = null;
        }

        if (!is_array($user) || ($user['type'] ?? null) !== 'employee') {
            return [null, null, null];
        }

        return [$user['id'] ?? null, $user['role']['id'] ?? null, $user['name'] ?? null];
    }

    /**
     * Log what an employee typed into the AI Research / AI Assistant chat
     * box. Those conversations are intentionally never audited as Eloquent
     * models — AiConversation/AiMessage only archive a completed turn's
     * text for the employee's own history (see their docblocks), and
     * nothing about AI usage is otherwise visible to admins — so this is
     * the only record of a prompt, and it must be written for every real
     * submission, including ones whose reply later errors or times out.
     */
    public static function logAiPrompt(
        string $module,
        string $auditableType,
        ?int $actorId,
        ?int $actorRoleId,
        ?string $actorName,
        string $conversationId,
        string $message,
        int $attachmentCount,
        string $modelTier,
        bool $resume = false
    ): void {
        $isResume = $resume && $message === '';
        $isAttachmentOnly = !$resume && $message === '' && $attachmentCount > 0;

        $description = match (true) {
            $isResume => "continued a {$module} conversation",
            $isAttachmentOnly => "sent {$attachmentCount} attachment(s) to {$module} with no message",
            default => sprintf('asked %s: "%s"', $module, Str::limit($message, 150)),
        };

        $recordLabel = match (true) {
            $isResume => '[continued]',
            $isAttachmentOnly => "[{$attachmentCount} attachment(s), no message]",
            default => Str::limit($message, 120),
        };

        static::record([
            'auditable_type' => $auditableType,
            'auditable_id'   => $actorId ?? 0,
            'module'         => $module,
            'event'          => 'created',
            'record_label'   => $recordLabel,
            'description'    => $description,
            'actor_id'       => $actorId,
            'actor_role_id'  => $actorRoleId,
            'actor_name'     => $actorName,
            'old_values'     => null,
            'new_values'     => [
                'conversation_id'  => $conversationId,
                'model_tier'       => $modelTier,
                'attachment_count' => $attachmentCount,
                'message'          => $message,
                'resume'           => $resume,
            ],
            'ip_address' => request()?->ip(),
        ]);
    }
}
