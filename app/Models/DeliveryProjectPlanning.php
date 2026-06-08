<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class DeliveryProjectPlanning extends Model
{
    use HasFactory;

    protected $table = 'delivery_project_planning';

    protected $fillable = [
        'delivery_projects_id',
        'phase_id',
        'activity_id',
        'parent_id',
        'stage_id',
        'is_group',
        'is_golive',
        'group_name',
        'weight',
        'start_date',
        'end_date',
        'actual_start_date',
        'actual_end_date',
        'status',
        'progress_percentage',
        'notes',
        'deliverable',
        'name',
        'level',
        'order_sequence',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'progress_percentage' => 'decimal:2',
        'weight' => 'decimal:2',
        'is_group' => 'boolean',
        'is_golive' => 'boolean',
    ];

    protected $appends = [
        'name',
        'description',
        'status_badge',
        'status_text',
        'duration_in_days',
        'actual_duration_in_days',
        'is_overdue',
        'calculated_progress',
        'calculated_weight'
    ];

    // =========================================================================
    // RELATIONSHIPS (ALL ORIGINAL + UPDATED activity relationship)
    // =========================================================================

    public function delivery_project(): BelongsTo
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    /**
     * Alias for delivery_project relationship
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    public function phase(): BelongsTo
    {
        return $this->belongsTo(DeliveryProjectPhase::class, 'phase_id');
    }

    /**
     * ✅ UPDATED: Now references project_activities table
     * This is the NEW primary relationship for activities
     */
    public function activity(): BelongsTo
    {
        return $this->belongsTo(DeliveryProjectActivity::class, 'activity_id');
    }

    /**
     * ✅ ORIGINAL: Get plannings by phase (preserved)
     */
    public function plannings(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'phase_id')
            ->orderBy('order_sequence');
    }

    /**
     * ✅ ORIGINAL: Get stages for this planning (preserved)
     */
    public function stages(): HasMany
    {
        return $this->hasMany(ActivityStage::class, 'planning_id')
                    ->orderBy('order_sequence');
    }

    /**
     * ✅ ORIGINAL: Belongs to a stage (preserved)
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(ActivityStage::class, 'stage_id');
    }

    /**
     * ✅ ORIGINAL: Get children (preserved)
     */
    public function children(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'parent_id')
                    ->orderBy('order_sequence');
    }

    /**
     * ✅ ORIGINAL: Get parent (preserved)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DeliveryProjectPlanning::class, 'parent_id');
    }

    /**
     * ✅ NEW: Get activities directly under group (without stage)
     * Used for Phase → Group → Activity hierarchy (skipping Stage)
     */
    public function directActivities(): HasMany
    {
        return $this->hasMany(DeliveryProjectPlanning::class, 'parent_id')
                    ->where('is_group', false)
                    ->whereNull('stage_id')
                    ->orderBy('order_sequence');
    }

    // =========================================================================
    // SCOPES (ALL ORIGINAL - PRESERVED)
    // =========================================================================

    public function scopeGroups($query)
    {
        return $query->where('is_group', true);
    }

    public function scopeActivities($query)
    {
        return $query->where('is_group', false);
    }

    public function scopeParents($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeInStage($query, $stageId)
    {
        return $query->where('stage_id', $stageId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    public function scopeByProject($query, $projectId)
    {
        return $query->where('delivery_projects_id', $projectId);
    }

    public function scopeByPhase($query, $phaseId)
    {
        return $query->where('phase_id', $phaseId);
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * ✅ NEW: Get plannings with activities (new structure)
     */
    public function scopeWithActivities($query)
    {
        return $query->whereNotNull('activity_id');
    }

    /**
     * ✅ NEW: Get plannings without activities (groups only)
     */
    public function scopeGroupsOnly($query)
    {
        return $query->where('is_group', true)->whereNull('activity_id');
    }

    // =========================================================================
    // CALCULATED PROGRESS (ALL ORIGINAL - PRESERVED)
    // =========================================================================

    public function getCalculatedProgressAttribute(): float
    {
        if ($this->is_group) {
            $stages = $this->stages;

            if ($stages->isEmpty()) {
                // ✅ FIX: check direct activities (Phase → Group → Activity, no stage)
                $directActivities = self::where('parent_id', $this->id)
                    ->where('is_group', false)
                    ->whereNull('stage_id')
                    ->with('activity')
                    ->get();

                if ($directActivities->isNotEmpty()) {
                    $totalWeight     = 0;
                    $weightedProgress = 0;

                    foreach ($directActivities as $pa) {
                        $weight   = (float) ($pa->weight ?? 0);
                        $progress = $pa->activity
                            ? (float) ($pa->activity->progress_percentage ?? $pa->progress_percentage ?? 0)
                            : (float) ($pa->progress_percentage ?? 0);

                        $totalWeight      += $weight;
                        $weightedProgress += $progress * $weight;
                    }

                    if ($totalWeight > 0) {
                        return round($weightedProgress / $totalWeight, 2);
                    }

                    // No weights set — simple average
                    $avg = $directActivities->avg(function ($pa) {
                        return $pa->activity
                            ? (float) ($pa->activity->progress_percentage ?? 0)
                            : (float) ($pa->progress_percentage ?? 0);
                    });
                    return round($avg ?? 0, 2);
                }

                return (float) ($this->progress_percentage ?? 0);
            }

            $totalWeight = $stages->sum('weight');

            if ($totalWeight == 0) {
                return round($stages->avg('progress') ?? 0, 2);
            }

            $weightedProgress = 0;
            foreach ($stages as $stage) {
                $stageProgress = $stage->calculated_progress ?? $stage->progress ?? 0;
                $weightedProgress += $stageProgress * ($stage->weight ?? 0);
            }

            return round($weightedProgress / $totalWeight, 2);
        }

        if (!$this->is_group && $this->children->isNotEmpty()) {
            $children = $this->children;
            $totalWeight = $children->sum('weight');
            
            if ($totalWeight == 0) {
                return round($children->avg('progress_percentage') ?? 0, 2);
            }

            $weightedProgress = 0;
            foreach ($children as $child) {
                $childProgress = $child->calculated_progress ?? $child->progress_percentage ?? 0;
                $weightedProgress += $childProgress * ($child->weight ?? 0);
            }

            return round($weightedProgress / $totalWeight, 2);
        }

        return (float) ($this->progress_percentage ?? 0);
    }

    public function getCalculatedWeightAttribute(): float
    {
        if ($this->is_group) {
            $stages = $this->stages;
            
            if ($stages->isEmpty()) {
                return (float) ($this->weight ?? 0);
            }

            return round($stages->sum('weight'), 2);
        }

        if (!$this->is_group && $this->children->isNotEmpty()) {
            $totalWeight = 0;
            foreach ($this->children as $child) {
                $totalWeight += $child->calculated_weight;
            }
            return round($totalWeight, 2);
        }

        return (float) ($this->weight ?? 0);
    }

    // =========================================================================
    // ACCESSORS (ALL ORIGINAL + UPDATED name/description for new structure)
    // =========================================================================

    /**
     * ✅ UPDATED: Get name (works with both old and new structure)
     */
    public function getNameAttribute(): string
    {
        // If explicit name is set, use it
        if (!empty($this->attributes['name'])) {
            return $this->attributes['name'];
        }

        if ($this->is_group) {
            return $this->group_name ?? 'Unnamed Group';
        }

        // ✅ NEW: Try activity from project_activities first
        if ($this->activity_id && $this->activity) {
            return $this->activity->name;
        }

        return 'Unknown Activity';
    }

    /**
     * Get description from linked activity
     */
    public function getDescriptionAttribute(): ?string
    {
        if ($this->is_group) {
            return null;
        }

        // Get description from project_activities
        if ($this->activity_id && $this->activity) {
            return $this->activity->description;
        }

        return null;
    }

    /**
     * ✅ ORIGINAL: Get status badge (preserved)
     */
    public function getStatusBadgeAttribute(): string
    {
        $badges = [
            'not_started' => 'bg-gray-100 text-gray-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'completed' => 'bg-green-100 text-green-800',
            'delayed' => 'bg-red-100 text-red-800',
        ];
        return $badges[$this->status] ?? 'bg-gray-100 text-gray-800';
    }

    /**
     * ✅ ORIGINAL: Get status text (preserved)
     */
    public function getStatusTextAttribute(): string
    {
        $texts = [
            'not_started' => 'Not Started',
            'in_progress' => 'In Progress',
            'completed'   => 'Completed',
            'delayed'     => 'Delayed',
        ];
        return $texts[$this->status] ?? 'Not Started';
    }

    /**
     * ✅ ORIGINAL: Get duration in days (preserved)
     */
    public function getDurationInDaysAttribute(): ?int
    {
        if ($this->is_group || !$this->start_date || !$this->end_date) {
            return null;
        }
        
        return $this->start_date->diffInDays($this->end_date) + 1;
    }

    /**
     * ✅ ORIGINAL: Get actual duration in days (preserved)
     */
    public function getActualDurationInDaysAttribute(): ?int
    {
        if ($this->is_group || !$this->actual_start_date || !$this->actual_end_date) {
            return null;
        }
        
        return $this->actual_start_date->diffInDays($this->actual_end_date) + 1;
    }

    /**
     * ✅ ORIGINAL: Check if overdue (preserved)
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'completed' || !$this->end_date) {
            return false;
        }
        
        return Carbon::now()->greaterThan($this->end_date);
    }

    // =========================================================================
    // WEIGHT VALIDATION METHODS (ALL ORIGINAL - PRESERVED)
    // =========================================================================

    /**
     * ✅ ORIGINAL: Validate stages weight (preserved)
     */
    public function validateStagesWeight(): bool
    {
        if (!$this->is_group) {
            return true;
        }

        $stages = $this->stages;
        
        if ($stages->isEmpty()) {
            return true;
        }

        $totalWeight = $stages->sum('weight');
        return abs($totalWeight - 100) < 0.01;
    }

    /**
     * ✅ ORIGINAL: Get stages weight validation message (preserved)
     */
    public function getStagesWeightValidationMessage(): string
    {
        if (!$this->is_group) {
            return '';
        }

        $stages = $this->stages;
        
        if ($stages->isEmpty()) {
            return 'ℹ️ No stages yet';
        }

        $totalWeight = $stages->sum('weight');
        
        if (abs($totalWeight - 100) < 0.01) {
            return '✅ Total weight valid (100%)';
        }
        
        return "⚠️ Total weight should be 100%, currently: " . round($totalWeight, 2) . "%";
    }

    /**
     * Recalculate group weight as sum of direct child activities' weights.
     * Called after any activity under this group is created, updated, or deleted.
     */
    public function recalculateGroupWeight(): void
    {
        if (!$this->is_group) {
            return;
        }

        $sumWeight = self::where('parent_id', $this->id)
            ->where('is_group', false)
            ->sum('weight');

        $this->weight = round((float) $sumWeight, 2);
        $this->saveQuietly();

        // Propagate up if this group itself is nested inside another group
        if ($this->parent_id) {
            $parentGroup = self::find($this->parent_id);
            if ($parentGroup && $parentGroup->is_group) {
                $parentGroup->recalculateGroupWeight();
            }
        }
    }

    /**
     * ✅ ORIGINAL: Get total activities weight (preserved)
     */
    public function getTotalActivitiesWeight(): float
    {
        if ($this->is_group) {
            return 0;
        }

        return $this->children->sum('weight');
    }

    /**
     * ✅ ORIGINAL: Validate activities weight (preserved)
     */
    public function validateActivitiesWeight(): bool
    {
        if ($this->is_group) {
            return true;
        }

        $total = $this->getTotalActivitiesWeight();
        
        if ($total == 0) {
            return true;
        }

        return abs($total - 100) < 0.01;
    }

    /**
     * ✅ ORIGINAL: Get activities weight validation message (preserved)
     */
    public function getActivitiesWeightValidationMessage(): string
    {
        $total = $this->getTotalActivitiesWeight();
        
        if ($total == 0) {
            return 'ℹ️ No activities yet';
        }
        
        if (abs($total - 100) < 0.01) {
            return '✅ Total weight valid (100%)';
        }
        
        return "⚠️ Total weight should be 100%, currently: {$total}%";
    }

    // =========================================================================
    // METHODS (ALL ORIGINAL - PRESERVED)
    // =========================================================================

    /**
     * ✅ ORIGINAL: Get hierarchy path (preserved)
     */
    public function getHierarchyPath(): string
    {
        $path = [$this->name];
        $current = $this->parent;
        
        while ($current) {
            array_unshift($path, $current->name);
            $current = $current->parent;
        }
        
        return implode(' > ', $path);
    }

    /**
     * ✅ ORIGINAL: Get all descendants (preserved)
     */
    public function getAllDescendants()
    {
        $descendants = collect();
        
        foreach ($this->children as $child) {
            $descendants->push($child);
            if ($child->is_group || $child->children->isNotEmpty()) {
                $descendants = $descendants->merge($child->getAllDescendants());
            }
        }
        
        return $descendants;
    }

    /**
     * ✅ ORIGINAL: Update group status (preserved)
     */
    public function updateGroupStatus(): void
    {
        if (!$this->is_group) {
            Log::warning("updateGroupStatus called on non-group item", ['id' => $this->id]);
            return;
        }

        $stages = $this->stages;

        // ✅ FIX: handle groups that use direct activities (no stages)
        if ($stages->isEmpty()) {
            $directActivities = self::where('parent_id', $this->id)
                ->where('is_group', false)
                ->whereNull('stage_id')
                ->with('activity')
                ->get();

            if ($directActivities->isEmpty()) {
                Log::info("Group has no stages or direct activities, keeping current status", ['group_id' => $this->id]);
                return;
            }

            $totalWeight      = 0;
            $weightedProgress = 0;
            $hasDelayed       = false;

            foreach ($directActivities as $pa) {
                $weight   = (float) ($pa->weight ?? 0);
                $progress = $pa->activity
                    ? (float) ($pa->activity->progress_percentage ?? $pa->progress_percentage ?? 0)
                    : (float) ($pa->progress_percentage ?? 0);
                $actStatus = $pa->activity ? ($pa->activity->status ?? $pa->status ?? 'not_started') : ($pa->status ?? 'not_started');

                $totalWeight      += $weight;
                $weightedProgress += $progress * $weight;

                if ($actStatus === 'delayed') {
                    $hasDelayed = true;
                }
            }

            $progress = $totalWeight > 0 ? round($weightedProgress / $totalWeight, 2) : 0;
            $this->progress_percentage = $progress;

            if ($progress == 0) {
                $this->status = 'not_started';
            } elseif ($progress >= 100) {
                $this->status = 'completed';
            } else {
                $this->status = $hasDelayed ? 'delayed' : 'in_progress';
            }

            Log::info("Group status updated (direct activities)", [
                'group_id'   => $this->id,
                'progress'   => $progress,
                'status'     => $this->status,
                'activities' => $directActivities->count(),
            ]);

            $this->saveQuietly();

            if ($this->parent_id) {
                $parent = $this->parent;
                if ($parent && $parent->is_group) {
                    $parent->updateGroupStatus();
                }
            }

            return;
        }

        // ── Existing: stage-based ──────────────────────────────────────────
        $progress = $this->calculated_progress;
        $this->progress_percentage = $progress;

        $calculatedWeight = $this->calculated_weight;
        $this->weight = $calculatedWeight;

        Log::info("Updating group status", [
            'group_id' => $this->id,
            'group_name' => $this->name,
            'stages_count' => $stages->count(),
            'calculated_progress' => $progress,
            'calculated_weight' => $calculatedWeight
        ]);

        if ($progress == 0) {
            $this->status = 'not_started';
        } elseif ($progress >= 100) {
            $this->status = 'completed';
        } else {
            $hasDelayed = $stages->contains('status', 'delayed');
            $this->status = $hasDelayed ? 'delayed' : 'in_progress';
        }

        Log::info("Group status updated", [
            'group_id' => $this->id,
            'progress' => $this->progress_percentage,
            'weight' => $this->weight,
            'status' => $this->status
        ]);

        $this->saveQuietly();

        if ($this->parent_id) {
            $parent = $this->parent;
            if ($parent && $parent->is_group) {
                Log::info("Updating parent group recursively", [
                    'parent_id' => $parent->id,
                    'parent_name' => $parent->name
                ]);
                $parent->updateGroupStatus();
            }
        }
    }

    /**
     * ✅ ORIGINAL: Update progress from stages (preserved)
     */
    public function updateProgressFromStages(): void
    {
        if (!$this->is_group) {
            return;
        }

        $this->updateGroupStatus();
    }

    /**
     * ✅ ORIGINAL: Update progress from children (preserved)
     */
    public function updateProgressFromChildren(): void
    {
        if ($this->is_group) {
            return;
        }

        $children = $this->children;
        
        if ($children->isEmpty()) {
            return;
        }

        $progress = $this->calculated_progress;
        $this->progress_percentage = $progress;

        if ($progress == 0) {
            $this->status = 'not_started';
        } elseif ($progress >= 100) {
            $this->status = 'completed';
        } else {
            $hasDelayed = $children->contains('status', 'delayed');
            $this->status = $hasDelayed ? 'delayed' : 'in_progress';
        }

        $this->saveQuietly();

        if ($this->stage_id) {
            $stage = $this->stage;
            if ($stage) {
                $stage->updateProgressFromActivities();
            }
        }
    }

    /**
     * ✅ ORIGINAL: Check if parent activity (preserved)
     */
    public function isParentActivity(): bool
    {
        return !$this->is_group && $this->children()->exists();
    }

    /**
     * ✅ ORIGINAL: Check if leaf activity (preserved)
     */
    public function isLeafActivity(): bool
    {
        return !$this->is_group && !$this->children()->exists();
    }

    /**
     * ✅ ORIGINAL: Get root group (preserved)
     */
    public function getRootGroup()
    {
        if (!$this->is_group) {
            return null;
        }

        $current = $this;
        while ($current->parent_id) {
            $parent = $current->parent;
            if (!$parent || !$parent->is_group) {
                break;
            }
            $current = $parent;
        }

        return $current;
    }

    /**
     * Get activity details from linked project activity
     */
    public function getActivityDetails()
    {
        if ($this->is_group) {
            return null;
        }

        // Get details from project_activities
        if ($this->activity_id && $this->activity) {
            return [
                'source' => 'delivery_project_activities',
                'id' => $this->activity->id,
                'name' => $this->activity->name,
                'description' => $this->activity->description,
                'module' => $this->activity->module,
                'complexity' => $this->activity->complexity,
                'deliverable' => $this->activity->deliverable,
            ];
        }

        return null;
    }

    // =========================================================================
    // EVENTS (UPDATED TO SUPPORT BOTH STRUCTURES)
    // =========================================================================

    protected static function boot()
    {
        parent::boot();

        static::saved(function ($planning) {
            if ($planning->project) {
                $planning->project->updateFromPlanning();
            }

            // ✅ ORIGINAL: Update stage dates and progress jika ini activity di dalam stage
            if ($planning->stage_id && !$planning->is_group) {
                $stage = $planning->stage;
                if ($stage) {
                    $stage->updateDatesFromActivities();
                    $stage->updateProgressFromActivities();
                }
            }

            // ✅ ORIGINAL: Update parent progress
            if ($planning->parent_id && !$planning->is_group) {
                $parent = $planning->parent;
                if ($parent && !$parent->is_group) {
                    $parent->updateProgressFromChildren();
                }
            }

            // ✅ NEW: Sync changes to project_activities if this is an activity reference
            if ($planning->activity_id && !$planning->is_group) {
                $activity = $planning->activity;
                if ($activity) {
                    // Sync basic fields back to activity
                    $syncData = [];
                    
                    if ($planning->isDirty('start_date')) {
                        $syncData['start_date'] = $planning->start_date;
                    }
                    if ($planning->isDirty('end_date')) {
                        $syncData['end_date'] = $planning->end_date;
                    }
                    if ($planning->isDirty('actual_start_date')) {
                        $syncData['actual_start_date'] = $planning->actual_start_date;
                    }
                    if ($planning->isDirty('actual_end_date')) {
                        $syncData['actual_end_date'] = $planning->actual_end_date;
                    }
                    if ($planning->isDirty('status')) {
                        $syncData['status'] = $planning->status;
                    }
                    if ($planning->isDirty('progress_percentage')) {
                        $syncData['progress_percentage'] = $planning->progress_percentage;
                    }
                    if ($planning->isDirty('weight')) {
                        $syncData['weight'] = $planning->weight;
                    }

                    if (!empty($syncData)) {
                        $activity->update($syncData);
                    }
                }
            }
        });

        static::deleting(function ($planning) {
            if ($planning->project) {
                $planning->project->updateFromPlanning();
            }

            // ✅ ORIGINAL: Delete stages if group
            if ($planning->is_group) {
                foreach ($planning->stages as $stage) {
                    $stage->delete();
                }
            }

            // ✅ ORIGINAL: Delete children
            foreach ($planning->children as $child) {
                $child->delete();
            }

            // ✅ ORIGINAL: Update stage setelah activity dihapus
            if ($planning->stage_id) {
                $stage = ActivityStage::find($planning->stage_id);
                if ($stage) {
                    $stage->updateDatesFromActivities();
                    $stage->updateProgressFromActivities();
                }
            }
        });
    }
}