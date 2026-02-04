<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * DeliveryActivity Model
 *
 * Aktivitas dalam delivery planning. Mendukung flexible parent:
 * - parent_type = 'stage'  → Activity di bawah Stage (via stage_id)
 * - parent_type = 'group'  → Activity langsung di bawah Group (via group_id)
 *
 * Hierarchy:
 * Phase → Group → Stage → Activity (traditional)
 * Phase → Group → Activity (tanpa stage) [NEW!]
 *
 * @property int $id
 * @property int $project_id
 * @property int $phase_id
 * @property string $parent_type enum: stage|group
 * @property int|null $stage_id
 * @property int|null $group_id
 * @property string $name
 * @property string|null $code
 * @property string|null $description
 * @property int $order_sequence
 * @property \Carbon\Carbon|null $planned_start_date
 * @property \Carbon\Carbon|null $planned_end_date
 * @property \Carbon\Carbon|null $actual_start_date
 * @property \Carbon\Carbon|null $actual_end_date
 * @property float $weight
 * @property string $status
 * @property float $progress_percentage
 * @property string|null $notes
 */
class DeliveryActivity extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'delivery_activities';

    protected $fillable = [
        'project_id',
        'phase_id',
        'parent_type',
        'stage_id',
        'group_id',
        'name',
        'code',
        'description',
        'order_sequence',
        'planned_start_date',
        'planned_end_date',
        'actual_start_date',
        'actual_end_date',
        'weight',
        'status',
        'progress_percentage',
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
     * Project
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * Phase
     */
    public function phase(): BelongsTo
    {
        return $this->belongsTo(DeliveryPhase::class, 'phase_id');
    }

    /**
     * Parent Stage (jika parent_type = 'stage')
     */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(DeliveryStage::class, 'stage_id');
    }

    /**
     * Parent Group (jika parent_type = 'group')
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(DeliveryGroup::class, 'group_id');
    }

    /**
     * Employees yang di-assign ke activity ini
     */
    public function assignedEmployees(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'activity_employees', 'activity_id', 'employee_id')
            ->withPivot(['role', 'allocation_percentage', 'assigned_date', 'is_active', 'notes'])
            ->withTimestamps();
    }

    /**
     * Active assigned employees only
     */
    public function activeAssignees(): BelongsToMany
    {
        return $this->assignedEmployees()->wherePivot('is_active', true);
    }

    /**
     * Timesheets untuk activity ini
     */
    public function timesheets(): HasMany
    {
        return $this->hasMany(Timesheet::class, 'activity_id');
    }

    // =========================================================================
    // SCOPES
    // =========================================================================

    /**
     * Activities di bawah Stage
     */
    public function scopeUnderStage($query)
    {
        return $query->where('parent_type', 'stage');
    }

    /**
     * Activities langsung di bawah Group
     */
    public function scopeUnderGroup($query)
    {
        return $query->where('parent_type', 'group');
    }

    /**
     * Activities untuk Stage tertentu
     */
    public function scopeForStage($query, $stageId)
    {
        return $query->where('parent_type', 'stage')->where('stage_id', $stageId);
    }

    /**
     * Activities langsung di Group tertentu (tanpa stage)
     */
    public function scopeDirectlyInGroup($query, $groupId)
    {
        return $query->where('parent_type', 'group')->where('group_id', $groupId);
    }

    /**
     * Filter by status
     */
    public function scopeWithStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Activities yang di-assign ke employee tertentu
     */
    public function scopeAssignedTo($query, $employeeId)
    {
        return $query->whereHas('assignedEmployees', function ($q) use ($employeeId) {
            $q->where('employee.employee_id', $employeeId)
              ->where('activity_employees.is_active', true);
        });
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_sequence');
    }

    // =========================================================================
    // METHODS
    // =========================================================================

    /**
     * Get parent (Stage atau Group tergantung parent_type)
     */
    public function getParent(): Model
    {
        return $this->parent_type === 'stage'
            ? $this->stage
            : $this->group;
    }

    /**
     * Update progress dan propagate ke parent
     */
    public function updateProgress(float $progress): void
    {
        $this->update([
            'progress_percentage' => round($progress, 2),
            'status' => $this->determineStatus($progress),
        ]);

        // Update parent (Stage atau Group)
        if ($this->parent_type === 'stage' && $this->stage) {
            $this->stage->updateProgressFromActivities();
        } elseif ($this->parent_type === 'group' && $this->group) {
            $this->group->updateProgress();
        }
    }

    /**
     * Mark as started
     */
    public function markAsStarted(): void
    {
        if (!$this->actual_start_date) {
            $this->update([
                'actual_start_date' => now(),
                'status' => 'in_progress',
            ]);
        }
    }

    /**
     * Mark as completed
     */
    public function markAsCompleted(): void
    {
        $this->update([
            'actual_end_date' => now(),
            'status' => 'completed',
            'progress_percentage' => 100,
        ]);

        // Update parent
        if ($this->parent_type === 'stage' && $this->stage) {
            $this->stage->updateProgressFromActivities();
        } elseif ($this->parent_type === 'group' && $this->group) {
            $this->group->updateProgress();
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
     * Assign employee ke activity
     */
    public function assignEmployee(int $employeeId, string $role = 'member', array $options = []): void
    {
        $this->assignedEmployees()->syncWithoutDetaching([
            $employeeId => array_merge([
                'role' => $role,
                'allocation_percentage' => 100,
                'assigned_date' => now(),
                'is_active' => true,
            ], $options)
        ]);
    }

    /**
     * Unassign employee dari activity
     */
    public function unassignEmployee(int $employeeId): void
    {
        $this->assignedEmployees()->updateExistingPivot($employeeId, [
            'is_active' => false,
        ]);
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
        if (!$this->actual_start_date) return null;
        $endDate = $this->actual_end_date ?? now();
        return $this->actual_start_date->diffInDays($endDate) + 1;
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
            'cancelled' => 'bg-gray-300 text-gray-600',
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
            'cancelled' => 'Cancelled',
            'not_started' => 'Not Started',
            default => ucfirst(str_replace('_', ' ', $this->status)),
        };
    }

    /**
     * Check if activity is overdue
     */
    public function getIsOverdueAttribute(): bool
    {
        if ($this->status === 'completed') return false;
        if (!$this->planned_end_date) return false;
        return $this->planned_end_date->isPast();
    }

    /**
     * Parent name for display
     */
    public function getParentNameAttribute(): string
    {
        if ($this->parent_type === 'stage') {
            return $this->stage?->name ?? 'Unknown Stage';
        }
        return $this->group?->name ?? 'Unknown Group';
    }

    /**
     * Full hierarchy path for display
     */
    public function getHierarchyPathAttribute(): string
    {
        $parts = [];

        if ($this->phase) {
            $parts[] = $this->phase->name;
        }

        if ($this->group) {
            $parts[] = $this->group->name;
        }

        if ($this->parent_type === 'stage' && $this->stage) {
            $parts[] = $this->stage->name;
        }

        return implode(' → ', $parts);
    }
}
