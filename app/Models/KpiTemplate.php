<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiTemplate extends Model
{
    protected $table = 'kpi_templates';

    protected $fillable = [
        'role_id',
        'name',
        'description',
        'period_type',
        'deadline_day',
        'deadline_month',
        'deadline_year',
        'target_type',
        'is_anonymous',
        'target_roles',
        'target_positions',
        'target_employees',
        'target_projects',
        'subject_employees',
        'score_divisor',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'          => 'boolean',
        'is_anonymous'       => 'boolean',
        'target_roles'       => 'array',
        'target_positions'   => 'array',
        'target_employees'   => 'array',
        'target_projects'    => 'array',
        'subject_employees'  => 'array',
        'score_divisor'      => 'integer',
    ];

    /**
     * Templates that already existed by the end of the given "Y-m" period. A
     * template starts applying in the month it was created, so browsing an
     * earlier month never shows (or generates evaluations for) a template that
     * didn't exist yet.
     */
    public function scopeStartedBy($query, string $periodMonth)
    {
        return $query->where('created_at', '<=', \Carbon\Carbon::createFromFormat('Y-m', $periodMonth)->endOfMonth());
    }

    /**
     * The fill-in deadline this template implies for a "Y-m" period, or null
     * when none is set. monthly: that day of the period's month; quarterly:
     * the chosen month/day of the period's year; annual: the exact date. Days
     * past the end of a short month are clamped (31 → 28/30).
     */
    public function deadlineFor(string $periodMonth): ?\Carbon\Carbon
    {
        if (!$this->deadline_day) {
            return null;
        }
        $period = \Carbon\Carbon::createFromFormat('Y-m', $periodMonth);

        [$year, $month] = match ($this->period_type) {
            'quarterly' => $this->deadline_month ? [$period->year, (int) $this->deadline_month] : [null, null],
            'annual'    => ($this->deadline_month && $this->deadline_year) ? [(int) $this->deadline_year, (int) $this->deadline_month] : [null, null],
            default     => [$period->year, $period->month],
        };
        if (!$year) {
            return null;
        }

        $first = \Carbon\Carbon::create($year, $month, 1)->startOfDay();
        return $first->copy()->day(min((int) $this->deadline_day, $first->daysInMonth));
    }

    /** Human label for the deadline, matching what the form collects per period type. */
    public function getDeadlineLabelAttribute(): ?string
    {
        if (!$this->deadline_day) {
            return null;
        }
        $month = $this->deadline_month ? \Carbon\Carbon::create(2000, (int) $this->deadline_month, 1)->format('F') : null;

        return match ($this->period_type) {
            'quarterly' => $month ? "{$month} {$this->deadline_day}" : null,
            'annual'    => ($month && $this->deadline_year) ? "{$this->deadline_day} {$month} {$this->deadline_year}" : null,
            default     => "day {$this->deadline_day} of each month",
        };
    }

    /**
     * Whether this template pins its counterpart ("for who" on Upward, "who
     * fills" on Lead/Peer) to an explicit employee list rather than
     * auto-deriving it from each matched employee's direct supervisor.
     */
    public function hasExplicitSubjects(): bool
    {
        return !empty($this->subject_employees);
    }

    public function subjectEmployeeIds(): array
    {
        return array_values(array_unique(array_map('intval', $this->subject_employees ?? [])));
    }

    /**
     * Whether this template is offered to a given employee. Targeting is a set
     * of OR criteria across roles, positions, individual employees and delivery
     * projects — any single match qualifies. An untargeted template (all lists
     * empty) applies to everyone.
     */
    public function appliesTo(Employee $employee): bool
    {
        $roles     = $this->target_roles ?? [];
        $positions = $this->target_positions ?? [];
        $employees = $this->target_employees ?? [];
        $projects  = $this->target_projects ?? [];

        if (empty($roles) && empty($positions) && empty($employees) && empty($projects)) {
            return true;
        }

        if (!empty($employees)
            && in_array((int) $employee->employee_id, array_map('intval', $employees), true)) {
            return true;
        }

        if (!empty($roles)) {
            $empRoleIds = $employee->relationLoaded('roles')
                ? $employee->roles->pluck('id')->all()
                : $employee->roles()->pluck('employee_role.id')->all();
            if (array_intersect(array_map('intval', $roles), array_map('intval', $empRoleIds))) {
                return true;
            }
        }

        if (!empty($positions)) {
            $pos = $employee->basicData?->position;
            if ($pos && in_array($pos, $positions, true)) {
                return true;
            }
        }

        if (!empty($projects)) {
            $empProjectIds = $employee->relationLoaded('deliveryProjects')
                ? $employee->deliveryProjects->pluck('id')->all()
                : $employee->deliveryProjects()->pluck('delivery_projects.id')->all();
            if (array_intersect(array_map('intval', $projects), array_map('intval', $empProjectIds))) {
                return true;
            }
        }

        return false;
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'role_id', 'id');
    }

    public function indicators()
    {
        return $this->hasMany(KpiIndicator::class, 'template_id')->orderBy('order_seq');
    }

    public function scoringScales()
    {
        return $this->hasMany(KpiScoringScale::class, 'template_id')->orderBy('scale_value');
    }

    public function evaluations()
    {
        return $this->hasMany(KpiEvaluation::class, 'template_id');
    }

    public function createdBy()
    {
        return $this->belongsTo(Employee::class, 'created_by', 'employee_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Total weight of all indicators (should equal 100).
     */
    public function getTotalWeightAttribute(): float
    {
        return (float) $this->indicators->sum('weight');
    }

    /**
     * The widget max and the divisor in "Weighted Score = Score ÷ divisor ×
     * Bobot" — the number of rows on this template's scoring scale (e.g. 5
     * rows defined = a 5-point scale), kept in sync at save time by
     * KpiTemplateController::resolveDivisor().
     */
    public function scaleMax(): int
    {
        if ($this->score_divisor) {
            return (int) $this->score_divisor;
        }
        if ($this->relationLoaded('scoringScales') && $this->scoringScales->isNotEmpty()) {
            return $this->scoringScales->count();
        }
        $count = $this->scoringScales()->count();
        return $count ?: 5;
    }

    /**
     * The template's scale rows, or the standard 5-point scale when none are
     * configured — always a Collection of row-shaped arrays/models, ordered
     * ascending (1 at top, highest value at bottom).
     */
    public function scaleRows()
    {
        $rows = $this->relationLoaded('scoringScales')
            ? $this->scoringScales
            : $this->scoringScales()->get();

        if ($rows->isNotEmpty()) {
            return $rows->sortBy('scale_value')->values();
        }

        return collect(KpiScoringScale::defaultRows())->map(fn ($r) => (object) $r);
    }

    /**
     * Period type label for display.
     */
    public function getPeriodTypeLabelAttribute(): string
    {
        return match ($this->period_type) {
            'monthly'   => 'Monthly',
            'quarterly' => 'Quarterly',
            'annual'    => 'Annual',
            default     => ucfirst($this->period_type),
        };
    }

    /**
     * Target evaluator role label.
     */
    public function getTargetTypeLabelAttribute(): string
    {
        return match ($this->target_type) {
            'self'       => 'Evaluasi Mandiri (Self-Assessment)',
            'supervisor' => 'Penilaian Atasan (Supervisor Evaluation)',
            'peer'       => 'Evaluasi Rekan Kerja (Peer Evaluation)',
            'upward'     => 'Evaluasi Atasan oleh Bawahan (Upward Evaluation)',
            default      => 'Penilaian Atasan',
        };
    }
}
