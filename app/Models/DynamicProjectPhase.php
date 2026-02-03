<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class DynamicProjectPhase extends Model
{
    use HasFactory;

    protected $table = 'project_phases';

    protected $fillable = [
        'name',
        'description',
        'order_sequence',
        'color',
        'weight',
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
        'settings' => 'array',
    ];

    // Relasi ke parent phase untuk fase horizontal
    public function parentPhase()
    {
        return $this->belongsTo(DynamicProjectPhase::class, 'parent_phase_id');
    }

    // Relasi ke child phases
    public function childPhases()
    {
        return $this->hasMany(DynamicProjectPhase::class, 'parent_phase_id');
    }

    // Relasi ke activities
    public function activities()
    {
        return $this->hasMany(ProjectActivity::class, 'project_phase_id')->orderBy('order_sequence');
    }

    // Relasi many-to-many dengan projects
    public function projects()
    {
        return $this->belongsToMany(Project::class, 'project_project_phase', 'project_phase_id', 'project_id')
            ->withPivot(['weight', 'order_sequence', 'is_visible', 'orientation', 'custom_settings'])
            ->withTimestamps();
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
        $plannings = ProjectPlanning::where('project_id', $projectId)
            ->whereHas('activity', function($query) {
                $query->where('project_phase_id', $this->id);
            })
            ->orWhereHas('customActivity', function($query) {
                $query->where('project_phase_id', $this->id);
            })
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
            $newActivity->project_phase_id = $newPhase->id;
            $newActivity->save();
        }

        return $newPhase;
    }
}