<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DeliveryProjectActivity extends Model
{
    use HasFactory;

    protected $table = 'delivery_project_activities';

    protected $fillable = [
        'delivery_projects_id',
        'delivery_project_phase_id',
        'stage_id',
        'name',
        'description',
        'order_sequence',
        'module',
        'new_requirement',
        'object',
        'receive_type',
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
    public function delivery_project(): BelongsTo
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    /**
     * Get the phase that owns the activity
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(DeliveryProjectPhase::class, 'delivery_project_phase_id');
    }

    /**
     * Get the stage that owns the activity
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ActivityStage::class, 'stage_id');
    }

    /**
     * Get the planning records associated with this activity (for backward compatibility)
     * During transition period, project_planning might still reference activities
     */
    public function delivery_plannings(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'activity_id');
    }

    /**
     * Get the employees assigned to this activity
     */
    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'activity_employee', 'delivery_project_activity_id', 'employee_id', 'id', 'employee_id')
                    ->withPivot('role', 'assigned_date', 'notes', 'duration')
                    ->withTimestamps();
    }

    /**
     * Get timesheets for this activity
     */
    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'activity_id');
    }

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
        return $query->where('delivery_project_phase_id', $phaseId);
    }

    /**
     * Scope untuk filter berdasarkan project
     */
    public function scopeByProject($query, $projectId)
    {
        return $query->where('delivery_projects_id', $projectId);
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

    /**
     * Compute status from current progress + dates.
     * Rules (Delayed checked first — takes precedence over Completed):
     *   1. actual_end > planned_end                                → delayed
     *   2. progress < 100 AND today > planned_end                  → delayed
     *   3. progress = 0   AND today > planned_start AND !actual_start → delayed
     *   4. progress = 100 AND actual_end set                       → completed
     *   5. progress = 100 AND actual_end empty                     → in_progress
     *   6. progress = 0                                            → not_started
     *   7. otherwise                                               → in_progress
     */
    public function computeStatus(?\Carbon\Carbon $today = null): string
    {
        $today    = $today ?? \Carbon\Carbon::today();
        $progress = (float) ($this->progress_percentage ?? 0);

        $ps = $this->start_date;
        $pe = $this->end_date;
        $as = $this->actual_start_date;
        $ae = $this->actual_end_date;

        // DELAYED (precedence)
        if ($ae && $pe && $ae->gt($pe))                           return 'delayed';
        if ($progress < 100 && $pe && $today->gt($pe))            return 'delayed';
        if ($progress == 0 && $ps && $today->gt($ps) && !$as)     return 'delayed';

        // COMPLETED
        if ($progress >= 100 && $ae)                              return 'completed';

        // IN_PROGRESS / NOT_STARTED
        if ($progress >= 100 && !$ae)                             return 'in_progress';
        if ($progress == 0)                                       return 'not_started';
        return 'in_progress';
    }

    /**
     * Auto-recompute status on save when progress/date fields change.
     */
    protected static function booted(): void
    {
        static::saving(function (self $activity) {
            $relevant = ['progress_percentage', 'start_date', 'end_date', 'actual_start_date', 'actual_end_date'];
            $changed  = false;
            foreach ($relevant as $f) {
                if ($activity->isDirty($f)) { $changed = true; break; }
            }
            // Also recompute if status itself is dirty (e.g. legacy direct write) so we always end up consistent
            if ($changed || $activity->isDirty('status') || !$activity->exists) {
                $activity->status = $activity->computeStatus();
            }
        });

        // Sync computed status back to the linked planning record (denormalized mirror)
        static::saved(function (self $activity) {
            if (!$activity->wasChanged('status')) return;

            $planning = DeliveryProjectPlanning::where('activity_id', $activity->id)->first();
            if ($planning && $planning->status !== $activity->status) {
                $planning->status = $activity->status;
                $planning->saveQuietly();
            }
        });
    }
}