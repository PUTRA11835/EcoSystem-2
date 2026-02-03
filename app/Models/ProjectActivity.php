<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProjectActivity extends Model
{
    use HasFactory;

    protected $table = 'project_activities';

    protected $fillable = [
        'project_id',
        'project_phase_id',
        'stage_id',
        'name',
        'description',
        'order_sequence',
        'module',
        'new_requirement',
        'tcode',
        'receive_type',
        'complexity',
        'functional_sinergi',
        'technical_sinergi',
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
        'new_requirement' => 'boolean',
        'progress_percentage' => 'decimal:2',
        'weight' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Get the project that owns the activity
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Get the phase that owns the activity
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(ProjectPhase::class, 'project_phase_id');
    }

    /**
     * Get the stage that owns the activity
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ActivityStage::class, 'stage_id');
    }

    /**
     * Get all stages for this activity (for backward compatibility with old structure)
     * This is for activities that have child stages
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ActivityStage::class, 'activity_id')
            ->orderBy('order_sequence');
    }

    /**
     * Get the planning records associated with this activity (for backward compatibility)
     * During transition period, project_planning might still reference activities
     */
    public function plannings(): HasMany
    {
        return $this->hasMany(ProjectPlanning::class, 'activity_id');
    }

    /**
     * Get the employees assigned to this activity
     */
    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'activity_employee', 'project_activity_id', 'employee_id', 'id', 'employee_id')
                    ->withPivot('role', 'assigned_date', 'notes')
                    ->withTimestamps();
    }

    /**
     * Get timesheets for this activity
     */
    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'activity_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Scope untuk mengurutkan berdasarkan order_sequence
     */
    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    /**
     * Scope untuk filter berdasarkan status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope untuk filter berdasarkan phase
     */
    public function scopeByPhase($query, $phaseId)
    {
        return $query->where('project_phase_id', $phaseId);
    }

    /**
     * Scope untuk filter berdasarkan project
     */
    public function scopeByProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    // =========================================================================
    // HELPER METHODS
    // =========================================================================

    /**
     * Calculate progress based on stages
     */
    public function calculateProgress(): float
    {
        $stages = $this->stages;
        
        if ($stages->isEmpty()) {
            return $this->progress_percentage ?? 0;
        }

        $totalWeight = $stages->sum('weight');
        
        if ($totalWeight == 0) {
            return $stages->avg('progress') ?? 0;
        }

        $weightedProgress = $stages->sum(function ($stage) {
            return ($stage->progress * $stage->weight) / 100;
        });

        return round(($weightedProgress / $totalWeight) * 100, 2);
    }

    /**
     * Update activity progress
     */
    public function updateProgress(): void
    {
        $progress = $this->calculateProgress();
        $this->update(['progress_percentage' => $progress]);
    }

    /**
     * Get completion percentage
     */
    public function getCompletionPercentageAttribute(): float
    {
        return $this->progress_percentage ?? 0;
    }

    /**
     * Check if activity is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === 'completed';
    }

    /**
     * Check if activity is in progress
     */
    public function isInProgress(): bool
    {
        return $this->status === 'in_progress';
    }

    /**
     * Check if activity is delayed
     */
    public function isDelayed(): bool
    {
        return $this->status === 'delayed';
    }
}