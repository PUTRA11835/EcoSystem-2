<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliverySupportPhase extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_phases';

    protected $fillable = [
        'delivery_support_id',
        'name',
        'description',
        'order_sequence',
        'color',
        'weight',
        'is_resolution_phase',
        'is_visible',
        'custom_settings',
        'is_system_default',
        'is_optional',
        'orientation',
        'is_active',
        'parent_phase_id',
        'settings',
    ];

    protected $casts = [
        'weight' => 'decimal:2',
        'is_resolution_phase' => 'boolean',
        'is_visible' => 'boolean',
        'is_system_default' => 'boolean',
        'is_optional' => 'boolean',
        'is_active' => 'boolean',
        'custom_settings' => 'array',
        'settings' => 'array',
    ];

    public function deliverySupport()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function parentPhase()
    {
        return $this->belongsTo(DeliverySupportPhase::class, 'parent_phase_id');
    }

    public function childPhases()
    {
        return $this->hasMany(DeliverySupportPhase::class, 'parent_phase_id')
            ->orderBy('order_sequence');
    }

    public function activities()
    {
        return $this->hasMany(DeliverySupportActivity::class, 'delivery_support_phase_id')
            ->orderBy('order_sequence');
    }

    public function planning()
    {
        return $this->hasMany(DeliverySupportPlanning::class, 'phase_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeVertical($query)
    {
        return $query->where('orientation', 'vertical');
    }

    public function scopeHorizontal($query)
    {
        return $query->where('orientation', 'horizontal');
    }

    public function calculateProgress()
    {
        $activities = $this->activities;
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

        return round($weightedProgress, 2);
    }
}
