<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiEvaluationDetail extends Model
{
    protected $table = 'kpi_evaluation_details';

    protected $fillable = [
        'evaluation_id',
        'indicator_id',
        'self_achievement',
        'actual_achievement',
        'self_notes',
        'self_submitted_at',
        'supervisor_score',
        'star_rating',
        'supervisor_notes',
        'supervisor_submitted_at',
        'weighted_score',
    ];

    protected $casts = [
        'self_achievement'        => 'float',
        'supervisor_score'        => 'float',
        'star_rating'             => 'integer',
        'weighted_score'          => 'float',
        'self_submitted_at'       => 'datetime',
        'supervisor_submitted_at' => 'datetime',
    ];

    // ── Relationships ────────────────────────────────────────────────────────

    public function evaluation()
    {
        return $this->belongsTo(KpiEvaluation::class, 'evaluation_id');
    }

    public function indicator()
    {
        return $this->belongsTo(KpiIndicator::class, 'indicator_id');
    }

    // ── Helpers ──────────────────────────────────────────────────────────────

    /**
     * Compute weighted_score from supervisor_score or star_rating and indicator weight.
     * Star rating 1 to 5 maps to 20, 40, 60, 80, 100 score.
     */
    public function computeWeightedScore(): void
    {
        if (!is_null($this->star_rating) && (is_null($this->supervisor_score) || $this->supervisor_score == 0)) {
            $this->supervisor_score = $this->star_rating * 20;
        } elseif (!is_null($this->supervisor_score) && is_null($this->star_rating)) {
            $this->star_rating = min(5, max(1, (int) round($this->supervisor_score / 20)));
        }

        if (!is_null($this->supervisor_score) && $this->indicator) {
            $this->weighted_score = round(
                ($this->indicator->weight * $this->supervisor_score) / 100,
                2
            );
            $this->saveQuietly();
        }
    }
}
