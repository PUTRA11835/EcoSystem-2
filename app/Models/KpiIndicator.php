<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiIndicator extends Model
{
    protected $table = 'kpi_indicators';

    protected $fillable = [
        'template_id',
        'name',
        'answer_type',
        'rating_max',
        'description',
        'measurement_unit',
        'target_value',
        'weight',
        'order_seq',
    ];

    protected $casts = [
        'target_value' => 'float',
        'weight'       => 'float',
        'rating_max'   => 'integer',
        'order_seq'    => 'integer',
    ];

    /**
     * A paragraph indicator collects free text only — no rating, no weight.
     */
    public function isParagraph(): bool
    {
        return ($this->answer_type ?? 'rating') === 'paragraph';
    }

    /**
     * Scale cap for this indicator: its own override, else the template's.
     */
    public function effectiveMax(): int
    {
        if ($this->rating_max) {
            return (int) $this->rating_max;
        }
        return (int) ($this->relationLoaded('template') && $this->template
            ? $this->template->scaleMax()
            : 5);
    }

    // ── Relationships ────────────────────────────────────────────────────────

    public function template()
    {
        return $this->belongsTo(KpiTemplate::class, 'template_id');
    }

    public function evaluationDetails()
    {
        return $this->hasMany(KpiEvaluationDetail::class, 'indicator_id');
    }
}
