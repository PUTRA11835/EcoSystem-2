<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupport extends Model
{
    use HasFactory;

    protected $table = 'delivery_support';

    protected $fillable = [
        'id_delivery_list',
        'client_id',
        'ticket_id',
        'start_date',
        'end_date',
        'resolution_estimated',
        'calculated_progress',
        'name',
        'delivery_owner_id',
        'support_manager_id',
        'support_method',
        'total_mandays',
        'created_by_id',
        'approval_date',
        'approval_name',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'resolution_estimated' => 'date',
        'approval_date' => 'date',
        'calculated_progress' => 'decimal:2',
    ];

    // ========================================
    // RELATIONSHIPS
    // ========================================

    public function deliveryList()
    {
        return $this->belongsTo(DeliveryList::class, 'id_delivery_list');
    }

    public function client()
    {
        return $this->belongsTo(Customer::class, 'client_id', 'customer_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function deliveryOwner()
    {
        return $this->belongsTo(Employee::class, 'delivery_owner_id', 'employee_id');
    }

    public function supportManager()
    {
        return $this->belongsTo(Employee::class, 'support_manager_id', 'employee_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by_id', 'employee_id');
    }

    public function phases()
    {
        return $this->hasMany(DeliverySupportPhase::class, 'delivery_support_id')
            ->orderBy('order_sequence');
    }

    public function visiblePhases()
    {
        return $this->phases()->where('is_visible', true);
    }

    public function viewConfiguration()
    {
        return $this->hasOne(DeliverySupportViewConfiguration::class, 'delivery_support_id');
    }

    public function planning()
    {
        return $this->hasMany(DeliverySupportPlanning::class, 'delivery_support_id');
    }

    public function activities()
    {
        return $this->hasMany(DeliverySupportActivity::class, 'delivery_support_id');
    }

    public function documents()
    {
        return $this->hasMany(DeliverySupportDocument::class, 'delivery_support_id');
    }

    public function updates()
    {
        return $this->hasMany(DeliverySupportUpdate::class, 'delivery_support_id');
    }

    /**
     * Get team members from the linked ticket
     */
    public function teamMembers()
    {
        if ($this->ticket_id) {
            return TicketTeam::where('ticket_id', $this->ticket_id)->with('employee');
        }
        return collect([]);
    }

    // ========================================
    // HELPER METHODS
    // ========================================

    public function calculateProgress()
    {
        $phases = $this->visiblePhases;
        if ($phases->isEmpty()) {
            return 0;
        }

        $totalWeight = $phases->sum('weight');
        if ($totalWeight <= 0) {
            return 0;
        }

        $weightedProgress = 0;
        foreach ($phases as $phase) {
            $phaseProgress = $this->calculatePhaseProgress($phase);
            $weightedProgress += ($phase->weight / $totalWeight) * $phaseProgress;
        }

        return round($weightedProgress, 2);
    }

    protected function calculatePhaseProgress($phase)
    {
        $activities = $this->activities()
            ->where('delivery_support_phase_id', $phase->id)
            ->get();

        if ($activities->isEmpty()) {
            return 0;
        }

        $totalWeight = $activities->sum('weight');
        if ($totalWeight <= 0) {
            return $activities->avg('progress_percentage') ?? 0;
        }

        $weightedProgress = 0;
        foreach ($activities as $activity) {
            $weightedProgress += ($activity->weight / $totalWeight) * $activity->progress_percentage;
        }

        return $weightedProgress;
    }

    public function updateCalculatedProgress()
    {
        $this->calculated_progress = $this->calculateProgress();
        $this->save();
    }

    // ========================================
    // SCOPES
    // ========================================

    public function scopeForClient($query, $clientId)
    {
        return $query->where('client_id', $clientId);
    }

    public function scopeActive($query)
    {
        return $query->where('calculated_progress', '<', 100);
    }

    public function scopeCompleted($query)
    {
        return $query->where('calculated_progress', '>=', 100);
    }

    // ========================================
    // ACCESSORS
    // ========================================

    public function getStatusAttribute()
    {
        if ($this->calculated_progress >= 100) {
            return 'completed';
        } elseif ($this->calculated_progress > 0) {
            return 'in_progress';
        }
        return 'not_started';
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            default => 'Not Started'
        };
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'completed' => 'green',
            'in_progress' => 'blue',
            default => 'gray'
        };
    }
}
