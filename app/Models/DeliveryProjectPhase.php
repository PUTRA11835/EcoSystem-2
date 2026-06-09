<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;  

class DeliveryProjectPhase extends Model
{
    use HasFactory;

    protected $table = 'delivery_project_phases';

    protected $fillable = [
        'delivery_projects_id',
        'name',
        'description',
        'order_sequence',
        'color',
        'weight',
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
        'is_system_default' => 'boolean',
        'is_optional' => 'boolean',
        'is_active' => 'boolean',
        'is_visible' => 'boolean',
        'settings' => 'array',
        'custom_settings' => 'array',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * ✅ BARU: One-to-Many - Phase has many Plannings (groups/activities)
     * Relationship ke ProjectPlanning melalui phase_id
     */
    public function plannings(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'phase_id')
            ->orderBy('order_sequence');
    }

    /**
     * ✅ Get only groups from plannings
     */
    public function groups(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'phase_id')
            ->where('is_group', true)
            ->whereNull('parent_id')
            ->orderBy('order_sequence');
    }

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    /**
     * ✅ Get only activities from plannings
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'phase_id')
            ->where('is_group', false)
            ->whereNull('parent_id')
            ->orderBy('order_sequence');
    }

    // Relasi ke parent phase untuk fase horizontal
    public function parentPhase()
    {
        return $this->belongsTo(DeliveryDynamicProjectPhase::class, 'parent_phase_id');
    }

    // Relasi ke child phases
    public function childPhases()
    {
        return $this->hasMany(DeliveryDynamicProjectPhase::class, 'parent_phase_id');
    }

    // Scope untuk fase vertikal
    public function scopeVertical($query)
    {
        return $query->where('orientation', 'vertical');
    }

    // Scope untuk fase horizontal
    public function scopeHorizontal($query)
    {
        return $query->where('orientation', 'horizontal');
    }
    
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence', 'asc');
    }
    // Scope untuk fase aktif
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // Scope untuk fase opsional
    public function scopeOptional($query)
    {
        return $query->where('is_optional', true);
    }

    // Method untuk mendapatkan semua aktivitas termasuk dari child phases
    public function getAllActivities()
    {
        $activities = collect($this->activities);
        
        foreach ($this->childPhases as $childPhase) {
            $activities = $activities->merge($childPhase->getAllActivities());
        }
        
        return $activities;
    }

    // Method untuk menghitung progress fase
    public function calculateProgress($projectId)
    {
        // Calculate progress from plannings in this phase
        $plannings = DeliveryProjectPlanning::where('delivery_projects_id', $projectId)
            ->where('phase_id', $this->id)
            ->get();

        if ($plannings->isEmpty()) {
            return 0;
        }

        return $plannings->avg('progress_percentage') ?? 0;
    }

    // Method untuk duplicate fase
    public function duplicate()
    {
        $newPhase = $this->replicate();
        $newPhase->name = $this->name . ' (Copy)';
        $newPhase->is_system_default = false;
        $newPhase->save();

        // Duplicate activities
        foreach ($this->activities as $activity) {
            $newActivity = $activity->replicate();
            $newActivity->delivery_project_phase_id = $newPhase->id;
            $newActivity->save();
        }

        return $newPhase;
    }
}