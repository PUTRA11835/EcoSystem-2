<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class DeliverySupportActivityStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_support_activity_stages';

    protected $fillable = [
        'planning_id',
        'activity_id',
        'name',
        'description',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'progress',
        'status',
        'weight',
        'custom_fields',
        'order_sequence',
        'color',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'progress' => 'decimal:2',
        'weight' => 'decimal:2',
        'custom_fields' => 'array',
    ];

    public function planning()
    {
        return $this->belongsTo(DeliverySupportPlanning::class, 'planning_id');
    }

    public function activity()
    {
        return $this->belongsTo(DeliverySupportActivity::class, 'activity_id');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeNotStarted($query)
    {
        return $query->where('status', 'not_started');
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
