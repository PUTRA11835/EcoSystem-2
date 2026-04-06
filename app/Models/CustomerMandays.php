<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerMandays extends Model
{
    use HasFactory;

    protected $table = 'customer_mandays';

    protected $fillable = [
        'ticket_id',
        'version',
        'proposed_by_agent_id',
        'proposed_at',
        'submitted_to_customer_at',
        'sent_to_chat_at',
        'status',
        'customer_notes',
        'rejection_reason',
        'notes',
        'total_mandays',
        'customer_response_at',
    ];

    protected $casts = [
        'proposed_at'              => 'datetime',
        'submitted_to_customer_at' => 'datetime',
        'sent_to_chat_at'          => 'datetime',
        'customer_response_at'     => 'datetime',
        'total_mandays'            => 'decimal:2',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function proposedByAgent()
    {
        return $this->belongsTo(Employee::class, 'proposed_by_agent_id', 'employee_id');
    }

    public function details()
    {
        return $this->hasMany(CustomerMandaysDetail::class, 'customer_mandays_id');
    }

    public function calculateTotalMandays()
    {
        return $this->details()->sum('mandays');
    }

    // Status scopes
    public function scopeDraft($query)           { return $query->where('status', 'draft'); }
    public function scopePendingHelpdesk($query) { return $query->where('status', 'pending_helpdesk'); }
    public function scopeSentToChat($query)      { return $query->where('status', 'sent_to_chat'); }
    public function scopeApproved($query)        { return $query->where('status', 'approved'); }
    public function scopeCanceled($query)        { return $query->where('status', 'canceled'); }

    public function scopeLatestVersion($query)
    {
        return $query->orderBy('version', 'desc');
    }

    /** Pivot detail ke array Activity → Module → Mandays */
    public function getMatrix(): array
    {
        $matrix = [];
        foreach ($this->details as $d) {
            $act = $d->activity ?? 'General';
            $matrix[$act][$d->module] = (float) $d->mandays;
        }
        return $matrix;
    }
}
