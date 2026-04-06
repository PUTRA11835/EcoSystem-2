<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DeliverySupportPlanning extends Model
{
    use HasFactory;

    protected $table = 'delivery_support_planning';

    protected $fillable = [
        'delivery_support_id',
        'phase_id',
        'parent_id',
        'stage_id',
        'activity_id',
        'name',
        'group_name',
        'is_group',
        'level',
        'order_sequence',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'weight',
        'status',
        'progress_percentage',
        'notes',
    ];

    protected $casts = [
        'is_group' => 'boolean',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'weight' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function phase()
    {
        return $this->belongsTo(DeliverySupportPhase::class, 'phase_id');
    }

    public function parent()
    {
        return $this->belongsTo(DeliverySupportPlanning::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DeliverySupportPlanning::class, 'parent_id')
            ->orderBy('order_sequence');
    }

    public function stage()
    {
        return $this->belongsTo(DeliverySupportActivityStage::class, 'stage_id');
    }

    public function activity()
    {
        return $this->belongsTo(DeliverySupportActivity::class, 'activity_id');
    }

    public function stages()
    {
        return $this->hasMany(DeliverySupportActivityStage::class, 'planning_id')
            ->orderBy('order_sequence');
    }

    public function scopeGroups($query)
    {
        return $query->where('is_group', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeByLevel($query, $level)
    {
        return $query->where('level', $level);
    }

    public function scopeByPhase($query, $phaseId)
    {
        return $query->where('phase_id', $phaseId);
    }

    public function getIsLeafAttribute()
    {
        return $this->children()->count() === 0;
    }

    public function calculateProgressFromChildren()
    {
        $children = $this->children;
        if ($children->isEmpty()) {
            return $this->progress_percentage;
        }

        $totalWeight = $children->sum('weight');
        if ($totalWeight <= 0) {
            return $children->avg('progress_percentage') ?? 0;
        }

        $weightedProgress = 0;
        foreach ($children as $child) {
            $weightedProgress += ($child->weight / $totalWeight) * $child->progress_percentage;
        }

        return round($weightedProgress, 2);
    }
}
