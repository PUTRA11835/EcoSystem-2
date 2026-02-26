<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'ticket';
    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'employee_id',
        'description',
        'start_date',
        'end_date',
        'ticket_priority',
        'ticket_type',
        'jarvies_status',
        'status',
        'wait_close',
        'folder',
        'file_log',
        'man_days',
        'channel',
        'email_thread_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'man_days' => 'decimal:2',
        'wait_close' => 'decimal:2',
    ];

    // Relasi ke Customer
    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    // Relasi ke Employee (PIC - Person In Charge)
    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id');
    }

    public function ticketMembers()
    {
        return $this->hasMany(TicketMember::class, 'ticket_id', 'ticket_id');
    }

    public function confirmation()
    {
        return $this->hasOne(TicketConfirmation::class, 'ticket_id', 'ticket_id')
            ->latest();
    }

    public function mandaysHistory()
    {
        return $this->hasMany(MandaysHistory::class, 'ticket_id', 'ticket_id')
            ->orderBy('created_at', 'desc');
    }


    // Relasi Many-to-Many ke Employee (Support Members/Pendamping)
    public function members()
    {
        return $this->belongsToMany(
            Employee::class,
            'ticket_member',
            'ticket_id',
            'employee_id',
            'ticket_id',
            'employee_id'
        )->withTimestamps();
    }

    // Scope untuk filter berdasarkan status
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeClosed($query)
    {
        return $query->where('status', 'closed');
    }

    // Scope untuk filter berdasarkan priority
    public function scopeHighPriority($query)
    {
        return $query->where('ticket_priority', 'High');
    }

    // Accessor untuk status label
    public function getStatusLabelAttribute()
    {
        return match($this->status) {
            'open' => 'Open',
            'in_progress' => 'In Progress',
            'hold' => 'Hold',
            'cancel' => 'Cancelled',
            'closed' => 'Closed',
            'reply' => 'Reply',
            default => 'Unknown'
        };
    }

    // Accessor untuk priority badge color
    public function getPriorityColorAttribute()
    {
        return match($this->ticket_priority) {
            'Low' => 'gray',
            'High' => 'red',
            'Medium' => 'blue',
            default => 'gray'
        };
    }

    // Relasi ke pesan-pesan tiket
    public function messages()
    {
        return $this->hasMany(TicketMessage::class, 'ticket_id', 'ticket_id')
            ->orderBy('created_at', 'asc');
    }

    // Relasi ke Delivery Support melalui activities
    public function deliverySupportActivities()
    {
        return $this->hasMany(DeliverySupportActivity::class, 'ticket_id', 'ticket_id');
    }

    // Get delivery supports via activities
    public function deliverySupports()
    {
        return DeliverySupport::whereHas('activities', function ($query) {
            $query->where('ticket_id', $this->ticket_id);
        });
    }
}