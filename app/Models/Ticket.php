<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Sanctum\HasApiTokens;

class Ticket extends Model
{
    use HasFactory;

    protected $table = 'ticket';
    protected $primaryKey = 'ticket_id';

    protected $fillable = [
        'ticket_number',
        'customer_id',
        'end_customer_id',
        'ticket_lead_id',
        'pic',
        'description',
        'start_date',
        'end_date',
        'ticket_priority',
        'ticket_type',
        'scale',
        'status',
        'wait_close',
        'folder',
        'file_log',
        'man_days',
        'channel',
        'email_thread_id',
        'last_message_at',
        'last_customer_reply_at',
        'last_agent_reply_at',
        'last_internal_note_at',
        'last_internal_note_sender_id',
        // Field tambahan dari form Jarvies
        'name',
        'no_hp',
        'module',
        'client',
        'submitted_by_email',
        'submitted_by_name',
        // Mandays status
        'mandays_proposal_status',
        'resolution_days_status',
        // CC recipients (disalin dari staging saat approve)
        'cc_emails',
        // Progress tracking
        'progress_percentage',
        'progress_note',
        'last_progress_at',
        'progress_updated_by',
        // OneDrive
        'onedrive_folder_id',
        'onedrive_folder_url',
        'onedrive_deliverable_folder_id',
        // Visibility
        'is_hidden',
    ];

    protected $casts = [
        'start_date'             => 'date',
        'end_date'               => 'date',
        'man_days'               => 'decimal:2',
        'wait_close'             => 'decimal:2',
        'progress_percentage'    => 'decimal:2',
        'last_message_at'        => 'datetime',
        'last_customer_reply_at' => 'datetime',
        'last_agent_reply_at'    => 'datetime',
        'last_internal_note_at'        => 'datetime',
        'last_internal_note_sender_id' => 'integer',
        'cc_emails'                    => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(Customer::class, 'customer_id', 'customer_id');
    }

    public function endCustomer()
    {
        return $this->belongsTo(Customer::class, 'end_customer_id', 'customer_id');
    }

    // Relasi ke Employee (Ticket Lead)
    public function ticketLead()
    {
        return $this->belongsTo(Employee::class, 'ticket_lead_id', 'employee_id');
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
    /** Active members only — digunakan di semua logika bisnis dan tampilan ticket list */
    public function members()
    {
        return $this->belongsToMany(
            Employee::class,
            'ticket_member',
            'ticket_id',
            'employee_id',
            'ticket_id',
            'employee_id'
        )->withTimestamps()->withPivot('is_active')->wherePivot('is_active', true);
    }

    /** Semua members (aktif + nonaktif) — digunakan untuk manajemen member di ticket detail */
    public function allMembers()
    {
        return $this->belongsToMany(
            Employee::class,
            'ticket_member',
            'ticket_id',
            'employee_id',
            'ticket_id',
            'employee_id'
        )->withTimestamps()->withPivot('is_active');
    }

    // Scope untuk filter berdasarkan status
    public function scopeOpen($query)
    {
        return $query->where('status', 'open');
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'inprocess');
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
        return match ($this->status) {
            'open'                    => 'Open',
            'inprocess'               => 'Inprocess',
            'waiting_on_customer'     => 'Waiting on Customer',
            'waiting_on_3rd_party'    => 'Waiting on 3rd Party',
            'waiting_to_confirmation' => 'Waiting to Confirmation',
            'hold'                    => 'Hold',
            'cancelled'               => 'Cancelled',
            'closed'                  => 'Closed',
            default                   => ucfirst($this->status ?? 'Unknown'),
        };
    }

    // Accessor untuk priority badge color
    public function getPriorityColorAttribute()
    {
        return match ($this->ticket_priority) {
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

    // ── SLA ──────────────────────────────────────────────────────────────────

    public function sla()
    {
        return $this->hasOne(TicketSla::class, 'ticket_id', 'ticket_id');
    }

    public function isSlaEligible(): bool
    {
        return $this->ticket_type !== null
            && $this->ticket_priority !== null
            && $this->scale !== null;
    }

    public function getSlaMode(): string
    {
        return in_array($this->ticket_type, self::slaFullTypes()) ? 'full' : 'response_only';
    }

    public static function slaFullTypes(): array
    {
        return ['Incident', 'Service Request'];
    }

    // Get delivery supports via activities
    public function deliverySupports()
    {
        return DeliverySupport::whereHas('activities', function ($query) {
            $query->where('ticket_id', $this->ticket_id);
        });
    }
}
