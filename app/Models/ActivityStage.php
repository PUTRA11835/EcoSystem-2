<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;
use App\Models\DeliveryProjectActivity;
use Carbon\Carbon;

class ActivityStage extends Model
{
    use SoftDeletes;

    protected $table = 'activity_stages';

    protected $fillable = [
        'planning_id',
        'activity_id',
        'name',
        'description',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'weight',
        'progress',
        'status',
        'color',
        'order_sequence',
        'custom_fields', 
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'weight' => 'decimal:2',
        'progress' => 'decimal:2',
        'order_sequence' => 'integer',
        'custom_fields' => 'array',
    ];

    protected $dates = [
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'created_at',
        'updated_at',
        'deleted_at',
    ];

    protected $appends = [
        'calculated_progress',
        'duration_days',
        'status_label',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * ✅ ORIGINAL: Stage belongs to a GROUP (parent) - for backward compatibility
     * This relationship is DEPRECATED but kept for backward compatibility
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(DeliveryProjectPlanning::class, 'planning_id');
    }

    /**
     * ✅ Alias untuk parent group (ORIGINAL - kept for backward compatibility)
     */
    public function group()
    {
        return $this->planning();
    }

    /**
     * ✅ UPDATED: Stage has many ACTIVITIES
     * Works with BOTH old and new structure
     */
    public function activities(): HasMany
    {
        // ✅ NEW: Prefer project_activities if available
        if ($this->projectActivities()->exists()) {
            return $this->projectActivities();
        }
        
        // ✅ FALLBACK: Old structure via ProjectPlanning
        return $this->hasMany(DeliveryProjectPlanning::class, 'stage_id')
            ->where('is_group', false)
            ->orderBy('order_sequence');
    }

    public function hasProjectActivities(): bool
    {
        return $this->projectActivities()->exists();
    }

    public function getAllActivities()
    {
        // Try new structure first
        $newActivities = $this->projectActivities;
        
        if ($newActivities->isNotEmpty()) {
            return $newActivities;
        }
        
        // Fallback to old structure
        return $this->hasMany(DeliveryProjectPlanning::class, 'stage_id')
            ->where('is_group', false)
            ->orderBy('order_sequence')
            ->get();
    }
    /**
     * ✅ ORIGINAL: Get only parent activities (tidak termasuk child activities)
     */
    public function parentActivities()
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'stage_id')
            ->whereNull('parent_id')
            ->whereNotNull('stage_id')
            ->orderBy('order_sequence');
    }

    /**
     * ✅ ORIGINAL: Get project reference
     */
    public function project()
    {
        if ($this->activity_id && $this->activity) {
            return $this->activity->project();
        }

        return $this->hasOneThrough(
            \App\Models\DeliveryProject::class,
            DeliveryProjectPlanning::class,
            'id',
            'id',
            'planning_id',
            'delivery_projects_id'
        );
    }

    public function projectActivities(): HasMany
    {
        return $this->hasMany(DeliveryProjectActivity::class, 'stage_id')
            ->orderBy('order_sequence');
    }

    /**
     * ✅ ORIGINAL: Calculate progress from activities (bottom-up weighted average)
     */
    public function getCalculatedProgressAttribute()
    {
        $activities = $this->activities;

        if ($activities->isEmpty()) {
            return $this->progress ?? 0;
        }

        $totalWeight = 0;
        $weightedProgress = 0;

        foreach ($activities as $activity) {
            $weight = $activity->weight ?? 0;
            $progress = $activity->calculated_progress ?? $activity->progress_percentage ?? 0;

            $totalWeight += $weight;
            $weightedProgress += ($progress * $weight);
        }

        if ($totalWeight == 0) {
            return round($activities->avg('progress_percentage') ?? 0, 2);
        }

        return round($weightedProgress / $totalWeight, 2);
    }

    /**
     * ✅ ORIGINAL: Calculate weight dari sum children weights
     */
    public function getCalculatedWeightAttribute()
    {
        $activities = $this->activities;
        
        if ($activities->isEmpty()) {
            return $this->weight ?? 0;
        }

        return round($activities->sum('weight'), 2);
    }

    /**
     * ✅ ORIGINAL: Get status badge color
     */
    public function getStatusColorAttribute()
    {
        $colors = [
            'not_started' => '#6B7280',
            'in_progress' => '#3B82F6',
            'completed' => '#10B981',
            'delayed' => '#EF4444',
            'on_hold' => '#F59E0B',
        ];

        return $colors[$this->status] ?? $colors['not_started'];
    }

    /**
     * ✅ ORIGINAL: Get status label
     */
    public function getStatusLabelAttribute()
    {
        $labels = [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'delayed' => 'Delayed',
            'on_hold' => 'On Hold',
        ];

        return $labels[$this->status] ?? 'Not Started';
    }

    /**
     * ✅ ORIGINAL: Get status badge CSS class
     */
    public function getStatusBadgeAttribute()
    {
        $badges = [
            'not_started' => 'bg-gray-100 text-gray-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'delayed' => 'bg-red-100 text-red-800',
            'on_hold' => 'bg-yellow-100 text-yellow-800',
        ];

        return $badges[$this->status] ?? $badges['not_started'];
    }

    /**
     * ✅ ORIGINAL: Check if stage is completed
     */
    public function getIsCompletedAttribute()
    {
        return $this->status === 'completed' || $this->progress >= 100;
    }

    /**
     * ✅ ORIGINAL: Check if stage is delayed
     */
    public function getIsDelayedAttribute()
    {
        if ($this->status === 'completed') {
            return false;
        }

        if (!$this->planned_end_date) {
            return false;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->planned_end_date);

        return $today->greaterThan($endDate) && $this->progress < 100;
    }

    /**
     * ✅ ORIGINAL: Get duration in days
     */
    public function getDurationDaysAttribute()
    {
        if (!$this->planned_start_date || !$this->planned_end_date) {
            return null;
        }

        $start = Carbon::parse($this->planned_start_date);
        $end = Carbon::parse($this->planned_end_date);

        return $start->diffInDays($end) + 1;
    }

    /**
     * ✅ ORIGINAL: Get planned duration (alias untuk backward compatibility)
     */
    public function getPlannedDurationAttribute()
    {
        return $this->duration_days;
    }

    public function scopeForGroup($query, $groupId)
    {
        return $query->where('planning_id', $groupId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at');
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

    public function scopeNotStarted($query)
    {
        return $query->where('status', 'not_started');
    }

    /**
     * ✅ NEW: Scope for stages linked to activities
     */
    public function scopeWithActivity($query)
    {
        return $query->whereNotNull('activity_id');
    }

    /**
     * ✅ ORIGINAL: Calculate dates dari activities (READ-ONLY, tidak save)
     */
    public function calculateDatesFromActivities()
    {
        $activities = $this->activities;

        if ($activities->isEmpty()) {
            return [
                'start' => $this->planned_start_date,
                'end' => $this->planned_end_date,
                'duration' => $this->duration_days
            ];
        }

        $allDates = collect();

        foreach ($activities as $activity) {
            if ($activity->start_date) {
                $allDates->push([
                    'date' => $activity->start_date,
                    'type' => 'start'
                ]);
            }
            
            if ($activity->end_date) {
                $allDates->push([
                    'date' => $activity->end_date,
                    'type' => 'end'
                ]);
            }
        }

        if ($allDates->isEmpty()) {
            return [
                'start' => $this->planned_start_date,
                'end' => $this->planned_end_date,
                'duration' => $this->duration_days
            ];
        }

        $startDate = $allDates->where('type', 'start')->pluck('date')->min();
        $endDate = $allDates->where('type', 'end')->pluck('date')->max();

        $duration = null;
        if ($startDate && $endDate) {
            $start = Carbon::parse($startDate);
            $end = Carbon::parse($endDate);
            $duration = $start->diffInDays($end) + 1;
        }

        return [
            'start' => $startDate ? Carbon::parse($startDate) : null,
            'end' => $endDate ? Carbon::parse($endDate) : null,
            'duration' => $duration
        ];
    }

    /**
     * ✅ ORIGINAL: Update dates stage dari activities (SAVE ke DB)
     */
    public function updateDatesFromActivities()
    {
        $dates = $this->calculateDatesFromActivities();

        $hasChanges = false;

        if ($dates['start'] && (!$this->planned_start_date || !$this->planned_start_date->eq($dates['start']))) {
            $this->planned_start_date = $dates['start'];
            $hasChanges = true;
        }

        if ($dates['end'] && (!$this->planned_end_date || !$this->planned_end_date->eq($dates['end']))) {
            $this->planned_end_date = $dates['end'];
            $hasChanges = true;
        }

        if ($hasChanges) {
            $this->saveQuietly();
            
            Log::info('Stage dates updated from activities', [
                'stage_id' => $this->id,
                'start' => $this->planned_start_date,
                'end' => $this->planned_end_date
            ]);
        }

        return $dates;
    }

    /**
     * ✅ ORIGINAL: Update progress stage dari activities (SAVE ke DB)
     */
    public function updateProgressFromActivities()
    {
        $calculatedProgress = $this->calculated_progress;
        
        $oldProgress = $this->progress;
        $this->progress = $calculatedProgress;
        
        // Auto-update status based on progress
        if ($calculatedProgress == 0) {
            $this->status = 'not_started';
        } elseif ($calculatedProgress >= 100) {
            $this->status = 'completed';
        } else {
            // Check if any activity is delayed
            $hasDelayed = $this->activities()->where('status', 'delayed')->exists();
            
            if ($hasDelayed || $this->is_delayed) {
                $this->status = 'delayed';
            } else {
                $this->status = 'in_progress';
            }
        }

        $this->saveQuietly();

        Log::info('Stage progress updated', [
            'stage_id' => $this->id,
            'name' => $this->name,
            'old_progress' => $oldProgress,
            'new_progress' => $this->progress,
            'status' => $this->status
        ]);

        return $this;
    }

    /**
     * ✅ ORIGINAL: Recalculate weight dari activities
     */
    public function updateWeightFromActivities()
    {
        $this->weight = $this->calculated_weight;
        $this->saveQuietly();

        return $this;
    }

    /**
     * ✅ ORIGINAL: Check and update status based on dates
     */
    public function checkAndUpdateStatus()
    {
        if ($this->status === 'completed') {
            return $this;
        }

        if (!$this->planned_end_date) {
            return $this;
        }

        $today = Carbon::today();
        $endDate = Carbon::parse($this->planned_end_date);

        if ($today->greaterThan($endDate) && $this->progress < 100) {
            $this->status = 'delayed';
            $this->saveQuietly();
        }

        return $this;
    }

    /**
     * ✅ ORIGINAL: FULL UPDATE - Update dates, progress, dan status sekaligus
     */
    public function fullUpdate()
    {
        Log::info('Stage full update started', [
            'stage_id' => $this->id,
            'name' => $this->name
        ]);

        // 1. Update dates dari activities
        $this->updateDatesFromActivities();
        
        // 2. Update progress dari activities
        $this->updateProgressFromActivities();
        
        // 3. Check and update status
        $this->checkAndUpdateStatus();

        // 4. Update parent group juga (works with both old and new structure)
        if ($this->planning_id) {
            $group = $this->planning;
            if ($group && $group->is_group) {
                $group->updateGroupStatus();
            }
        }
        
        // 5. ✅ NEW: Update parent activity if exists
        if ($this->activity_id) {
            $activity = $this->activity;
            if ($activity) {
                $activity->updateProgress();
            }
        }

        Log::info('Stage full update completed', [
            'stage_id' => $this->id,
            'progress' => $this->progress,
            'status' => $this->status,
            'start' => $this->planned_start_date,
            'end' => $this->planned_end_date
        ]);

        return $this->fresh();
    }

    // =========================================================================
    // HELPER METHODS (ALL ORIGINAL - PRESERVED)
    // =========================================================================

    /**
     * ✅ ORIGINAL: Check if stage is overdue
     */
    public function isOverdue()
    {
        if (!$this->planned_end_date || $this->status === 'completed') {
            return false;
        }

        return $this->planned_end_date->isPast() && $this->progress < 100;
    }

    /**
     * ✅ ORIGINAL: Get remaining days
     */
    public function getRemainingDays()
    {
        if (!$this->planned_end_date || $this->status === 'completed') {
            return null;
        }

        $today = Carbon::today();
        if ($this->planned_end_date < $today) {
            return 0; // Overdue
        }

        return $today->diffInDays($this->planned_end_date);
    }

    /**
     * ✅ ORIGINAL: Get progress percentage formatted
     */
    public function getFormattedProgress()
    {
        return number_format($this->progress, 1) . '%';
    }

    /**
     * ✅ NEW: Get parent (works with both old and new structure)
     */
    public function getParent()
    {
        // Try activity first (new structure)
        if ($this->activity_id) {
            return $this->activity;
        }
        
        // Fallback to planning (old structure)
        return $this->planning;
    }

    // =========================================================================
    // EVENTS (UPDATED TO SUPPORT BOTH STRUCTURES)
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        // Auto-set order_sequence if not provided
        static::creating(function ($stage) {
            if (!$stage->order_sequence) {
                // Try activity_id first (new structure)
                if ($stage->activity_id) {
                    $maxSequence = static::where('activity_id', $stage->activity_id)
                        ->max('order_sequence') ?? 0;
                } else {
                    // Fallback to planning_id (old structure)
                    $maxSequence = static::where('planning_id', $stage->planning_id)
                        ->max('order_sequence') ?? 0;
                }
                $stage->order_sequence = $maxSequence + 1;
            }

            // Set default color if not provided
            if (!$stage->color) {
                $colors = ['#3B82F6', '#8B5CF6', '#EC4899', '#F59E0B', '#10B981', '#06B6D4'];
                $stage->color = $colors[array_rand($colors)];
            }

            // Set default status
            if (!$stage->status) {
                $stage->status = 'not_started';
            }

            // Set default progress
            if (is_null($stage->progress)) {
                $stage->progress = 0;
            }
        });

        // ✅ UPDATED: After creating/updating stage, update parent (both structures)
        static::saved(function ($stage) {
            // Update parent activity (new structure)
            if ($stage->activity_id) {
                $activity = DeliveryProjectActivity::find($stage->activity_id);
                if ($activity) {
                    Log::info('Updating parent activity after stage save', [
                        'stage_id' => $stage->id,
                        'activity_id' => $activity->id
                    ]);
                    $activity->updateProgress();
                }
            }
            
            // Update parent group (old structure)
            if ($stage->planning_id) {
                $group = DeliveryProjectPlanning::find($stage->planning_id);
                if ($group && $group->is_group) {
                    Log::info('Updating parent group after stage save', [
                        'stage_id' => $stage->id,
                        'group_id' => $group->id
                    ]);
                    $group->updateGroupStatus();
                }
            }
        });

        // ✅ UPDATED: After deleting stage, update parent (both structures)
        static::deleted(function ($stage) {
            // Update parent activity (new structure)
            if ($stage->activity_id) {
                $activity = DeliveryProjectActivity::find($stage->activity_id);
                if ($activity) {
                    Log::info('Updating parent activity after stage delete', [
                        'stage_id' => $stage->id,
                        'activity_id' => $activity->id
                    ]);
                    $activity->updateProgress();
                }
            }
            
            // Update parent group (old structure)
            if ($stage->planning_id) {
                $group = DeliveryProjectPlanning::find($stage->planning_id);
                if ($group && $group->is_group) {
                    Log::info('Updating parent group after stage delete', [
                        'stage_id' => $stage->id,
                        'group_id' => $group->id
                    ]);
                    $group->updateGroupStatus();
                }
            }
        });
    }
}