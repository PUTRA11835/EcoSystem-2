<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ConsultantMandays extends Model
{
    use HasFactory;

    protected $table = 'consultant_mandays';

    protected $fillable = [
        'ticket_id',
        'proposed_by_agent_id',
        'proposed_at',
        'last_edited_at',
        'status',
        'approved_by_head_id',
        'approved_at',
        'rejection_reason',
        'total_mandays',
    ];

    protected $casts = [
        'proposed_at' => 'datetime',
        'last_edited_at' => 'datetime',
        'approved_at' => 'datetime',
        'total_mandays' => 'decimal:2',
    ];

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function proposedByAgent()
    {
        return $this->belongsTo(Employee::class, 'proposed_by_agent_id', 'employee_id');
    }

    public function approvedByHead()
    {
        return $this->belongsTo(Employee::class, 'approved_by_head_id', 'employee_id');
    }

    public function details()
    {
        return $this->hasMany(ConsultantMandaysDetail::class, 'consultant_mandays_id');
    }

    public function calculateTotalMandays()
    {
        return $this->details()->sum('mandays');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePendingApproval($query)
    {
        return $query->where('status', 'pending_approval');
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }

    public function scopeRejected($query)
    {
        return $query->where('status', 'rejected');
    }
}
