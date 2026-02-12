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
        'status',
        'customer_response_at',
        'customer_notes',
        'total_mandays',
    ];

    protected $casts = [
        'proposed_at' => 'datetime',
        'submitted_to_customer_at' => 'datetime',
        'customer_response_at' => 'datetime',
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

    public function details()
    {
        return $this->hasMany(CustomerMandaysDetail::class, 'customer_mandays_id');
    }

    public function calculateTotalMandays()
    {
        return $this->details()->sum('mandays');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopePendingCustomer($query)
    {
        return $query->where('status', 'pending_customer');
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
