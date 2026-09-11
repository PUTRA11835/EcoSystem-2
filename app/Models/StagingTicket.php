<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StagingTicket extends Model
{
    protected $table = 'staging_tickets';

    protected $fillable = [
        'customer_id',
        'end_customer_id',
        'description',
        'body',
        'ticket_priority',
        'ticket_type',
        'scale',
        'status',
        'rejection_reason',
        'channel',
        'email_thread_id',
        'submitted_by_email',
        // Kolom email baru (dari migration 2026_02_25_000001)
        'email_message_id',
        'graph_message_id',
        'sender_name',
        'email_body_html',
        'has_attachments',
        'cc_emails',
        'validated_by',
        'validated_at',
        'ticket_id',
        // Field tambahan dari form Jarvies (opsional)
        'name',
        'no_hp',
        'module',
        'module_id',
        'client',
        'created_at',
        'updated_at',
        'ai_analysis',
        'ai_analysis_generated_at',
        'ai_analysis_generated_by',
        'ai_analysis_status',
    ];

    protected $casts = [
        'validated_at'             => 'datetime',
        'has_attachments'          => 'boolean',
        'cc_emails'                => 'array',
        'ai_analysis'              => 'array',
        'ai_analysis_generated_at' => 'datetime',
    ];

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeUnvalidated($query)
    {
        return $query->where('status', 'unvalidated');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }

    // ─── Status helpers ───────────────────────────────────────────────────────

    public function isUnvalidated(): bool
    {
        return $this->status === 'unvalidated';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isProcessed(): bool
    {
        return in_array($this->status, ['approved', 'rejected']);
    }

    // ─── Relations ────────────────────────────────────────────────────────────

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function endCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'end_customer_id', 'customer_id');
    }

    public function validator(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'validated_by', 'employee_id');
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function moduleMaster(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'module_id', 'id');
    }

    public function getModuleNameAttribute(): ?string
    {
        return $this->moduleMaster?->name ?? $this->module;
    }

    public function attachments()
    {
        return $this->hasMany(StagingAttachment::class, 'staging_id');
    }
}
