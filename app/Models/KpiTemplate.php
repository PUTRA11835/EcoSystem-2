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
        'target_type',
        'target_roles',
        'target_positions',
        'target_employees',
        'target_projects',
        'score_divisor',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active'         => 'boolean',
        'target_roles'      => 'array',
        'target_positions'  => 'array',
        'target_employees'  => 'array',
        'target_projects'   => 'array',
        'score_divisor'     => 'integer',
    ];

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
        return $this->hasMany(KpiScoringScale::class, 'template_id')->orderByDesc('scale_value');
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
     * Highest value on this template's scoring scale — the widget max and the
     * divisor in "Weighted Score = Score ÷ divisor × Bobot".
     */
    public function scaleMax(): int
    {
        if ($this->score_divisor) {
            return (int) $this->score_divisor;
        }
        if ($this->relationLoaded('scoringScales') && $this->scoringScales->isNotEmpty()) {
            return (int) $this->scoringScales->max('scale_value');
        }
        $max = $this->scoringScales()->max('scale_value');
        return (int) ($max ?: 5);
    }

    /**
     * The template's scale rows, or the standard 5-point scale when none are
     * configured — always a Collection of row-shaped arrays/models.
     */
    public function scaleRows()
    {
        $rows = $this->relationLoaded('scoringScales')
            ? $this->scoringScales
            : $this->scoringScales()->get();

        if ($rows->isNotEmpty()) {
            return $rows->sortByDesc('scale_value')->values();
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
            default      => 'Penilaian Atasan',
        };
    }
}
