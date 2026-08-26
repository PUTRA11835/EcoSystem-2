<?php

namespace App\Models\Attendance;

use App\Models\DeliveryProject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Titik geofence lokasi kerja sebuah delivery project (kantor klien).
 *
 * Karyawan mana yang dievaluasi terhadap lokasi ini tidak disimpan di sini —
 * sumbernya `delivery_project_employee` yang sudah ada.
 */
class ProjectSite extends Model
{
    use SoftDeletes;

    protected $table = 'project_sites';

    protected $fillable = [
        'delivery_projects_id', 'name', 'address',
        'latitude', 'longitude', 'radius_meters',
        'is_active', 'created_by',
    ];

    protected $casts = [
        'latitude'      => 'decimal:8',
        'longitude'     => 'decimal:8',
        'radius_meters' => 'integer',
        'is_active'     => 'boolean',
    ];

    public const RADIUS_MIN = 20;
    public const RADIUS_MAX = 5000;

    // ── Relationships ───────────────────────────────────────────────────────

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id', 'id');
    }

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
