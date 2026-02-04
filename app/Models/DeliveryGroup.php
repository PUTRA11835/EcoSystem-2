<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DeliveryGroup Model
 *
 * Grup dalam fase delivery. Mendukung:
 * - Sub-groups unlimited level (via parent_id)
 * - Stages di dalam grup (optional)
 * - Activities langsung di grup (tanpa stage)
 *
 * Hierarchy:
 * Phase → Group → Sub-Group → ...
 *              ├── Stage → Activity
 *              └── Activity (langsung)
 *
 * @property int $id
 * @property int $project_id
 * @property int $phase_id
 * @property int|null $parent_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int $level
 * @property int $order_sequence
 * @property string|null $path
 * @property \Carbon\Carbon|null $planned_start_date
 * @property \Carbon\Carbon|null $planned_end_date
 * @property \Carbon\Carbon|null $actual_start_date
 * @property \Carbon\Carbon|null $actual_end_date
 * @property float $weight
 * @property string $status
 * @property float $progress_percentage
 * @property string|null $color
 * @property string|null $icon
 * @property string|null $notes
 */
class DeliveryGroup extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_groups';

    protected $fillable = [
        'project_id',
        'phase_id',
        'parent_id',
        'name',
        'code',
        'description',
        'level',
        'order_sequence',
        'path',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'weight',
        'status',
        'progress_percentage',
        'color',
        'icon',
        'notes',
    ];

    protected $casts = [
        'planned_start_date' => 'date',
        'planned_end_date' => 'date',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
        'weight' => 'decimal:2',
        'progress_percentage' => 'decimal:2',
    ];

    // =========================================================================
    // RELATIONSHIPS
    // =========================================================================

    /**
     * Project yang memiliki grup ini
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Phase yang memiliki grup ini
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(DeliveryPhase::class, 'phase_id');
    }

    /**
     * Parent group (untuk sub-group)
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(DeliveryGroup::class, 'parent_id');
    }

    /**
     * Child groups (sub-groups)
     */
    public function children(): HasMany
    {
        return $this->hasMany(DeliveryGroup::class, 'parent_id')->orderBy('order_sequence');
    }

    /**
     * All descendants (recursive)
     */
    public function descendants(): HasMany
    {
        return $this->hasMany(DeliveryGroup::class, 'parent_id')->with('descendants');
    }

    /**
     * Stages dalam grup ini
     */
    public function stages(): HasMany
    {
        return $this->hasMany(DeliveryStage::class, 'group_id')->orderBy('order_sequence');
    }

    /**
     * Activities langsung dalam grup ini (TANPA stage)
     */
    public function directActivities(): HasMany
    {
        return $this->hasMany(DeliveryActivity::class, 'group_id')
            ->where('parent_type', 'group')
            ->orderBy('order_sequence');
    }

    /**
     * All activities (baik via stage maupun langsung)
     */
    public function allActivities(): HasMany
    {
        return $this->hasMany(DeliveryActivity::class, 'group_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeRootGroups($query)
    {
        return $query->whereNull('parent_id');
    }

    public function scopeForPhase($query, $phaseId)
    {
        return $query->where('phase_id', $phaseId);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Update materialized path saat hierarchy berubah
     */
    public function updatePath(): void
    {
        if ($this->parent_id) {
            $parentPath = $this->parent->path ?? '';
            $this->path = $parentPath . $this->id . '/';
        } else {
            $this->path = $this->id . '/';
        }
        $this->saveQuietly();

        // Update children paths recursively
        foreach ($this->children as $child) {
            $child->updatePath();
        }
    }

    /**
     * Get semua activities (dari stages + langsung)
     */
    public function getAllActivitiesAttribute()
    {
        $activitiesFromStages = collect();

        foreach ($this->stages as $stage) {
            $activitiesFromStages = $activitiesFromStages->merge($stage->activities);
        }

        return $activitiesFromStages->merge($this->directActivities);
    }

    /**
     * Update progress dari children (stages + activities + sub-groups)
     */
    public function updateProgress(): void
    {
        $items = collect();

        // Collect dari sub-groups
        foreach ($this->children as $subGroup) {
            $items->push([
                'weight' => $subGroup->weight,
                'progress' => $subGroup->progress_percentage,
            ]);
        }

        // Collect dari stages
        foreach ($this->stages as $stage) {
            $items->push([
                'weight' => $stage->weight,
                'progress' => $stage->progress_percentage,
            ]);
        }

        // Collect dari direct activities
        foreach ($this->directActivities as $activity) {
            $items->push([
                'weight' => $activity->weight,
                'progress' => $activity->progress_percentage,
            ]);
        }

        if ($items->isEmpty()) {
            $this->update(['progress_percentage' => 0, 'status' => 'not_started']);
            return;
        }

        $totalWeight = $items->sum('weight');
        $weightedProgress = 0;

        if ($totalWeight > 0) {
            foreach ($items as $item) {
                $weightedProgress += ($item['progress'] * $item['weight']) / $totalWeight;
            }
        } else {
            $weightedProgress = $items->avg('progress');
        }

        $this->update([
            'progress_percentage' => round($weightedProgress, 2),
            'status' => $this->determineStatus($weightedProgress),
        ]);

        // Update parent group jika ada
        if ($this->parent) {
            $this->parent->updateProgress();
        }

        // Update phase
        if ($this->phase) {
            $this->phase->updateProgressFromGroups();
        }
    }

    /**
     * Determine status based on progress
     */
    protected function determineStatus(float $progress): string
    {
        if ($progress >= 100) return 'completed';
        if ($progress > 0) return 'in_progress';
        return 'not_started';
    }

    /**
     * Update dates from children
     */
    public function updateDates(): void
    {
        $dates = collect();

        // From stages
        foreach ($this->stages as $stage) {
            if ($stage->planned_start_date) $dates->push(['start' => $stage->planned_start_date]);
            if ($stage->planned_end_date) $dates->push(['end' => $stage->planned_end_date]);
        }

        // From direct activities
        foreach ($this->directActivities as $activity) {
            if ($activity->planned_start_date) $dates->push(['start' => $activity->planned_start_date]);
            if ($activity->planned_end_date) $dates->push(['end' => $activity->planned_end_date]);
        }

        // From sub-groups
        foreach ($this->children as $subGroup) {
            if ($subGroup->planned_start_date) $dates->push(['start' => $subGroup->planned_start_date]);
            if ($subGroup->planned_end_date) $dates->push(['end' => $subGroup->planned_end_date]);
        }

        if ($dates->isEmpty()) return;

        $startDates = $dates->pluck('start')->filter();
        $endDates = $dates->pluck('end')->filter();

        $this->update([
            'planned_start_date' => $startDates->min(),
            'planned_end_date' => $endDates->max(),
        ]);

        // Update phase dates
        if ($this->phase) {
            $this->phase->updateDatesFromGroups();
        }
    }

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Duration in days
     */
    public function getDurationInDaysAttribute(): ?int
    {
        if (!$this->planned_start_date || !$this->planned_end_date) return null;
        return $this->planned_start_date->diffInDays($this->planned_end_date) + 1;
    }

    /**
     * Actual duration in days
     */
    public function getActualDurationAttribute(): ?int
    {
        if (!$this->actual_start_date || !$this->actual_end_date) return null;
        return $this->actual_start_date->diffInDays($this->actual_end_date) + 1;
    }

    /**
     * Status badge class for UI
     */
    public function getStatusBadgeAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'bg-green-100 text-green-800',
            'in_progress' => 'bg-blue-100 text-blue-800',
            'delayed' => 'bg-red-100 text-red-800',
            'on_hold' => 'bg-yellow-100 text-yellow-800',
            default => 'bg-gray-100 text-gray-800',
        };
    }

    /**
     * Status text for UI
     */
    public function getStatusTextAttribute(): string
    {
        return match ($this->status) {
            'completed' => 'Completed',
            'in_progress' => 'In Progress',
            'delayed' => 'Delayed',
            'on_hold' => 'On Hold',
            'not_started' => 'Not Started',
            default => ucfirst($this->status),
        };
    }

    /**
     * Check if group has any children (stages, activities, or sub-groups)
     */
    public function getHasChildrenAttribute(): bool
    {
        return $this->children()->exists()
            || $this->stages()->exists()
            || $this->directActivities()->exists();
    }
}
