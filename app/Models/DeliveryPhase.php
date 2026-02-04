<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DeliveryPhase Model
 *
 * Phase dibuat langsung per project (tanpa template global).
 *
 * Hierarchy:
 * Project → Phase → Group → Stage → Activity
 *
 * @property int $id
 * @property int $project_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int $order_sequence
 * @property \Carbon\Carbon|null $planned_start_date
 * @property \Carbon\Carbon|null $planned_end_date
 * @property \Carbon\Carbon|null $actual_start_date
 * @property \Carbon\Carbon|null $actual_end_date
 * @property float $weight
 * @property float $progress_percentage
 * @property string $status
 * @property string $color
 * @property string|null $icon
 */
class DeliveryPhase extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_phases';

    protected $fillable = [
        'project_id',
        'name',
        'code',
        'description',
        'order_sequence',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'weight',
        'progress_percentage',
        'status',
        'color',
        'icon',
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
     * Project yang memiliki fase ini
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Groups dalam fase ini
     */
    public function groups(): HasMany
    {
        return $this->hasMany(DeliveryGroup::class, 'phase_id')->orderBy('order_sequence');
    }

    /**
     * Root groups only (tanpa parent)
     */
    public function rootGroups(): HasMany
    {
        return $this->hasMany(DeliveryGroup::class, 'phase_id')
            ->whereNull('parent_id')
            ->orderBy('order_sequence');
    }

    /**
     * Activities dalam fase ini
     */
    public function activities(): HasMany
    {
        return $this->hasMany(DeliveryActivity::class, 'phase_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    public function scopeForProject($query, $projectId)
    {
        return $query->where('project_id', $projectId);
    }

    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Update progress dari groups
     */
    public function updateProgressFromGroups(): void
    {
        $groups = $this->rootGroups;

        if ($groups->isEmpty()) {
            $this->update([
                'progress_percentage' => 0,
                'status' => 'not_started',
            ]);
            return;
        }

        $totalWeight = $groups->sum('weight');
        $weightedProgress = 0;

        if ($totalWeight > 0) {
            foreach ($groups as $group) {
                $weightedProgress += ($group->progress_percentage * $group->weight) / $totalWeight;
            }
        } else {
            $weightedProgress = $groups->avg('progress_percentage') ?? 0;
        }

        $this->update([
            'progress_percentage' => round($weightedProgress, 2),
            'status' => $this->determineStatus($weightedProgress),
        ]);
    }

    /**
     * Update dates dari groups
     */
    public function updateDatesFromGroups(): void
    {
        $groups = $this->rootGroups;

        if ($groups->isEmpty()) return;

        $startDates = $groups->pluck('planned_start_date')->filter();
        $endDates = $groups->pluck('planned_end_date')->filter();
        $actualStarts = $groups->pluck('actual_start_date')->filter();
        $actualEnds = $groups->pluck('actual_end_date')->filter();

        $this->update([
            'planned_start_date' => $startDates->min(),
            'planned_end_date' => $endDates->max(),
            'actual_start_date' => $actualStarts->min(),
            'actual_end_date' => $actualEnds->max(),
        ]);
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
     * Display name with code
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->code ? "[{$this->code}] {$this->name}" : $this->name;
    }

    /**
     * Check if phase has any groups
     */
    public function getHasGroupsAttribute(): bool
    {
        return $this->groups()->exists();
    }
}
