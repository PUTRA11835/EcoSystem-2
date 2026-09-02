<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KpiIndicator extends Model
{
    protected $table = 'kpi_indicators';

    protected $fillable = [
        'template_id',
        'name',
        'description',
        'measurement_unit',
        'target_value',
        'weight',
        'order_seq',
    ];

    protected $casts = [
        'target_value' => 'float',
        'weight'       => 'float',
        'order_seq'    => 'integer',
    ];

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
