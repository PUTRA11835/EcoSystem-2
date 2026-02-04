<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DeliveryStage Model
 *
 * Tahapan dalam grup. Stage adalah OPTIONAL - activity bisa langsung
 * di bawah group tanpa melalui stage.
 *
 * Hierarchy:
 * Group → Stage → Activity
 * Group → Activity (tanpa Stage)
 *
 * @property int $id
 * @property int $group_id
 * @property int $project_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int $order_sequence
 * @property string|null $color
 * @property \Carbon\Carbon|null $planned_start_date
 * @property \Carbon\Carbon|null $planned_end_date
 * @property \Carbon\Carbon|null $actual_start_date
 * @property \Carbon\Carbon|null $actual_end_date
 * @property float $weight
 * @property string $status
 * @property float $progress_percentage
 */
class DeliveryStage extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_stages';

    protected $fillable = [
        'group_id',
        'project_id',
        'name',
        'code',
        'description',
        'order_sequence',
        'color',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'weight',
        'status',
        'progress_percentage',
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
     * Grup yang memiliki stage ini
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DeliveryGroup::class, 'group_id');
    }

    /**
     * Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Activities dalam stage ini
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DeliveryActivity::class, 'stage_id')
            ->where('parent_type', 'stage')
            ->orderBy('order_sequence');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    public function scopeForGroup($query, $groupId)
    {
        return $query->where('group_id', $groupId);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Update progress dari activities
     */
    public function updateProgressFromActivities(): void
    {
        $activities = $this->activities;

        if ($activities->isEmpty()) {
            $this->update(['progress_percentage' => 0, 'status' => 'not_started']);
            return;
        }

        $totalWeight = $activities->sum('weight');
        $weightedProgress = 0;

        if ($totalWeight > 0) {
            foreach ($activities as $activity) {
                $weightedProgress += ($activity->progress_percentage * $activity->weight) / $totalWeight;
            }
        } else {
            $weightedProgress = $activities->avg('progress_percentage');
        }

        $status = $this->determineStatus($weightedProgress);

        $this->update([
            'progress_percentage' => round($weightedProgress, 2),
            'status' => $status,
        ]);

        // Update parent group
        $this->group->updateProgress();
    }

    /**
     * Update dates dari activities
     */
    public function updateDatesFromActivities(): void
    {
        $activities = $this->activities;

        if ($activities->isEmpty()) return;

        $startDate = $activities->min('planned_start_date');
        $endDate = $activities->max('planned_end_date');
        $actualStart = $activities->min('actual_start_date');
        $actualEnd = $activities->max('actual_end_date');

        $this->update([
            'planned_start_date' => $startDate,
            'planned_end_date' => $endDate,
            'actual_start_date' => $actualStart,
            'actual_end_date' => $actualEnd,
        ]);

        // Update parent group dates
        $this->group->updateDates();
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

    // =========================================================================
    // ACCESSORS
    // =========================================================================

    /**
     * Duration in days (planned)
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
     * Default color if not set
     */
    public function getDisplayColorAttribute(): string
    {
        return $this->color ?? '#F59E0B';
    }

    /**
     * Start date (prefer planned, fallback to actual)
     */
    public function getStartDateAttribute()
    {
        return $this->planned_start_date ?? $this->actual_start_date;
    }

    /**
     * End date (prefer planned, fallback to actual)
     */
    public function getEndDateAttribute()
    {
        return $this->planned_end_date ?? $this->actual_end_date;
    }
}
