<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiEvaluation extends Model
{
    protected $table = 'kpi_evaluations';

    protected $fillable = [
        'employee_id',
        'template_id',
        'period_month',
        'supervisor_id',
        'status',
        'is_anonymous',
        'overall_score',
        'general_notes',
        'self_deadline',
        'supervisor_deadline',
        'self_assessed_at',
        'reviewed_at',
        'hr_approved_at',
        'hr_approved_by',
        'hr_notes',
        'published_at',
        'published_by',
        'created_by',
    ];

    protected $casts = [
        'overall_score'       => 'float',
        'is_anonymous'        => 'boolean',
        'self_deadline'       => 'date',
        'supervisor_deadline' => 'date',
        'self_assessed_at'    => 'datetime',
        'reviewed_at'         => 'datetime',
        'hr_approved_at'      => 'datetime',
        'published_at'        => 'datetime',
    ];

    // ── Status constants ─────────────────────────────────────────────────────

    const STATUS_DRAFT        = 'draft';
    const STATUS_SELF_ASSESSED = 'self_assessed';
    const STATUS_REVIEWED     = 'reviewed';
    const STATUS_COMPLETED    = 'completed';
    const STATUS_HR_APPROVED  = 'hr_approved';
    const STATUS_HR_REJECTED  = 'hr_rejected';

    // ── Relationships ────────────────────────────────────────────────────────

    public function employee()
    {
        return $this->belongsTo(Employee::class, 'employee_id', 'employee_id')
                    ->with('basicData');
    }

    public function supervisor()
    {
        return $this->belongsTo(Employee::class, 'supervisor_id', 'employee_id')
                    ->with('basicData');
    }

    public function template()
    {
        return $this->belongsTo(KpiTemplate::class, 'template_id');
    }

    public function details()
    {
        return $this->hasMany(KpiEvaluationDetail::class, 'evaluation_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(Employee::class, 'hr_approved_by', 'employee_id')
                    ->with('basicData');
    }

    public function publishedBy()
    {
        return $this->belongsTo(Employee::class, 'published_by', 'employee_id')
                    ->with('basicData');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Assessment kind, derived from the linked template's target_type.
     * A "self" row is filled by the employee; a "lead" row (supervisor/peer)
     * is filled by the direct manager. An "upward" row is filled by the
     * employee too (rating their supervisor, stored in supervisor_id) — it
     * reuses the self_* fields but is its own category, kept out of both the
     * self-assessment and lead-assessment groupings.
     * Each is an independent evaluation.
     */
    public function isSelfType(): bool
    {
        return ($this->template?->target_type ?? 'supervisor') === 'self';
    }

    public function isUpwardType(): bool
    {
        return ($this->template?->target_type ?? 'supervisor') === 'upward';
    }

    public function isLeadType(): bool
    {
        return !$this->isSelfType() && !$this->isUpwardType();
    }

    /**
     * Whether this row is filled via the self_* fields (self-assessment
     * pathway) rather than the supervisor_* fields — true for both "self"
     * and "upward" rows, since both are filled by employee_id about
     * themselves or, for upward, about their supervisor.
     */
    protected function usesSelfFields(): bool
    {
        return $this->isSelfType() || $this->isUpwardType();
    }

    /**
     * Whether the employee has submitted their self-assessment.
     */
    public function hasSelfAssessment(): bool
    {
        return !is_null($this->self_assessed_at);
    }

    /**
     * Whether the supervisor has submitted their review.
     */
    public function hasSupervisorReview(): bool
    {
        return !is_null($this->reviewed_at);
    }

    /**
     * Whether the assessment relevant to this row's type is complete.
     * This is the prerequisite for HR to approve.
     *   - self row  → the employee's self-assessment is in
     *   - lead row  → the manager's review is in
     */
    public function isReadyForApproval(): bool
    {
        return $this->usesSelfFields()
            ? $this->hasSelfAssessment()
            : $this->hasSupervisorReview();
    }

    /**
     * Human-readable status label.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT         => 'Draft',
            self::STATUS_SELF_ASSESSED => 'Self-Assessed',
            self::STATUS_REVIEWED      => 'Reviewed',
            self::STATUS_COMPLETED     => 'Completed',
            self::STATUS_HR_APPROVED   => 'Approved',
            self::STATUS_HR_REJECTED   => 'Rejected',
            default                    => ucfirst($this->status),
        };
    }

    /**
     * CSS color class for status badge.
     */
    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            self::STATUS_DRAFT         => 'gray',
            self::STATUS_SELF_ASSESSED => 'blue',
            self::STATUS_REVIEWED      => 'yellow',
            self::STATUS_COMPLETED     => 'orange',
            self::STATUS_HR_APPROVED   => 'green',
            self::STATUS_HR_REJECTED   => 'red',
            default                    => 'gray',
        };
    }

    /**
     * Recalculate and persist the overall score from detail weighted scores.
     * overall_score = Σ(indicator_weight × supervisor_score / 100)
     */
    public function recalculateScore(): void
    {
        $details = $this->details()->with('indicator')->get();

        // Self/upward rows score off the filler's own achievement input; lead
        // rows off the manager's rating. Each row only ever carries one of the two.
        $field = $this->usesSelfFields() ? 'self_achievement' : 'supervisor_score';

        $totalScore = $details->sum(function ($detail) use ($field) {
            if (is_null($detail->$field)) return 0;
            return ($detail->indicator->weight * $detail->$field) / 100;
        });

        $this->overall_score = round($totalScore, 2);
        $this->saveQuietly();
    }

    /**
     * Determine the correct status based on what has been submitted.
     * Called after self-assessment or supervisor review is submitted.
     */
    public function refreshStatus(): void
    {
        $hasSelf = $this->hasSelfAssessment();
        $hasSupv = $this->hasSupervisorReview();

        if ($this->status === self::STATUS_HR_APPROVED || $this->status === self::STATUS_HR_REJECTED) {
            // HR decision locks the status — do not override
            return;
        }

        // Self/upward and lead rows are independent: each completes on its own
        // input, never waiting on the other side.
        if ($this->usesSelfFields()) {
            $this->status = $hasSelf ? self::STATUS_COMPLETED : self::STATUS_DRAFT;
        } else {
            $this->status = $hasSupv ? self::STATUS_COMPLETED : self::STATUS_DRAFT;
        }

        $this->saveQuietly();
    }
}
