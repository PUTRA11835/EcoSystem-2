<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row of a KPI template's "SKALA PENILAIAN" (scoring scale).
 * Admins edit these per template; scoring/display reads them back.
 */
class KpiScoringScale extends Model
{
    protected $table = 'kpi_scoring_scales';

    protected $fillable = [
        'template_id',
        'scale_value',
        'category',
        'definition',
        'achievement_label',
        'achievement_min',
        'achievement_max',
        'description',
        'order_seq',
    ];

    protected $casts = [
        'scale_value'     => 'integer',
        'achievement_min' => 'float',
        'achievement_max' => 'float',
        'order_seq'       => 'integer',
    ];

    public function template()
    {
        return $this->belongsTo(KpiTemplate::class, 'template_id');
    }

    /**
     * The standard 5-point scale from the reference form, used as the default
     * when a template has no custom scale rows of its own.
     */
    public static function defaultRows(): array
    {
        return [
            ['scale_value' => 5, 'category' => 'Outstanding',          'definition' => 'Jauh melampaui ekspektasi', 'achievement_label' => '>= 120%',    'achievement_min' => 120, 'achievement_max' => null, 'description' => 'Kinerja sangat istimewa',        'order_seq' => 1],
            ['scale_value' => 4, 'category' => 'Exceeds Expectations',  'definition' => 'Melampaui ekspektasi',      'achievement_label' => '105% - 119%', 'achievement_min' => 105, 'achievement_max' => 119, 'description' => 'Kinerja di atas target',          'order_seq' => 2],
            ['scale_value' => 3, 'category' => 'Meets Expectations',    'definition' => 'Memenuhi ekspektasi',       'achievement_label' => '90% - 104%',  'achievement_min' => 90,  'achievement_max' => 104, 'description' => 'Target tercapai secara memadai',  'order_seq' => 3],
            ['scale_value' => 2, 'category' => 'Needs Improvement',     'definition' => 'Di bawah ekspektasi',       'achievement_label' => '70% - 89%',   'achievement_min' => 70,  'achievement_max' => 89,  'description' => 'Perlu perbaikan terukur',         'order_seq' => 4],
            ['scale_value' => 1, 'category' => 'Unsatisfactory',        'definition' => 'Jauh di bawah ekspektasi',  'achievement_label' => '< 70%',      'achievement_min' => null, 'achievement_max' => 69,  'description' => 'Kinerja belum memenuhi standar', 'order_seq' => 5],
        ];
    }
}
