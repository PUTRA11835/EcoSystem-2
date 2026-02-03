<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProjectPlanningExtended extends Model
{
    use HasFactory;

    /**
     * Nama tabel yang terhubung dengan model ini.
     *
     * @var string
     */
    protected $table = 'project_planning_extended';

    /**
     * Atribut yang dapat diisi secara massal.
     *
     * @var array
     */
    protected $fillable = [
        'project_planning_id',
        'module',
        'new_requirement',
        'tcode',
        'receive_type',
        'complexity',
        'functional_sinergi',
        'technical_sinergi',
        'deliverable',
        'actual_start_date',
        'actual_end_date',
    ];

    /**
     * Casting tipe data untuk atribut.
     *
     * @var array
     */
    protected $casts = [
        'new_requirement' => 'boolean',
        'actual_start_date' => 'date',
        'actual_end_date' => 'date',
    ];

    /**
     * Mendefinisikan relasi "belongsTo" ke model ProjectPlanning.
     */
    public function planning(): BelongsTo
    {
        return $this->belongsTo(ProjectPlanning::class, 'project_planning_id');
    }
}