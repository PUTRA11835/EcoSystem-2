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
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function role()
    {
        return $this->belongsTo(EmployeeRole::class, 'role_id', 'id');
    }

    public function indicators()
    {
        return $this->hasMany(KpiIndicator::class, 'template_id')->orderBy('order_seq');
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
