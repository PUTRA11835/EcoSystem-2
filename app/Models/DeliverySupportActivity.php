<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupportActivity extends Model
{
    use HasFactory;

    protected $table = 'delivery_support_activities';

    protected $fillable = [
        'delivery_support_id',
        'delivery_support_phase_id',
        'ticket_id',
        'stage_id',
        'name',
        'description',
        'order_sequence',
        'module',
        'new_issue',
        'object',
        'incident_type',
        'complexity',
        'deliverable',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'progress_percentage',
        'weight',
        'notes',
    ];

    protected $casts = [
        'new_issue' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'progress_percentage' => 'decimal:2',
        'weight' => 'decimal:2',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function phase()
    {
        return $this->belongsTo(DeliverySupportPhase::class, 'delivery_support_phase_id');
    }

    public function stage()
    {
        return $this->belongsTo(DeliverySupportActivityStage::class, 'stage_id');
    }

    public function ticket()
    {
        return $this->belongsTo(Ticket::class, 'ticket_id', 'ticket_id');
    }

    public function stages()
    {
        return $this->hasMany(DeliverySupportActivityStage::class, 'activity_id')
            ->orderBy('order_sequence');
    }

    public function employees()
    {
        return $this->belongsToMany(Employee::class, 'delivery_support_activity_employee', 'delivery_support_activity_id', 'employee_id', 'id', 'employee_id')
            ->withPivot('role', 'allocation_percentage', 'is_active', 'assigned_date', 'notes')
            ->withTimestamps();
    }

    public function activeEmployees()
    {
        return $this->employees()->wherePivot('is_active', true);
    }

    public function planning()
    {
        return $this->hasMany(DeliverySupportPlanning::class, 'activity_id');
    }

    public function scopeByPhase($query, $phaseId)
    {
        return $query->where('delivery_support_phase_id', $phaseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeInProgress($query)
    {
        return $query->where('status', 'in_progress');
    }

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }

    public function scopeDelayed($query)
    {
        return $query->where('status', 'delayed');
    }

    public function calculateProgressFromStages()
    {
        $stages = $this->stages;
        if ($stages->isEmpty()) {
            return $this->progress_percentage;
        }

        $totalWeight = $stages->sum('weight');
        if ($totalWeight <= 0) {
            return $stages->avg('progress') ?? 0;
        }

        $weightedProgress = 0;
        foreach ($stages as $stage) {
            $weightedProgress += ($stage->weight / $totalWeight) * $stage->progress;
        }

        return round($weightedProgress, 2);
    }

    public function getStatusLabelAttribute()
    {
        return match ($this->status) {
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'delayed' => 'Delayed',
            'on_hold' => 'On Hold',
            default => 'Unknown'
        };
    }

    public function getStatusColorAttribute()
    {
        return match ($this->status) {
            'completed' => 'green',
            'in_progress' => 'blue',
            'delayed' => 'red',
            'on_hold' => 'yellow',
            default => 'gray'
        };
    }
}
