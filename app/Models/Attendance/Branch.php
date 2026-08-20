<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Cabang / kantor perusahaan beserta titik geofence-nya.
 *
 * Dipakai AttendanceService untuk memutuskan apakah sebuah presensi terjadi
 * di dalam area kantor. Lihat GeofenceService untuk perhitungan jaraknya.
 */
class Branch extends Model
{
    use SoftDeletes;

    protected $table = 'branches';

    protected $fillable = [
        'code', 'name', 'city', 'province', 'address', 'phone',
        'latitude', 'longitude', 'radius_meters', 'geofence_override',
        'is_head_office', 'home_base_key', 'is_active', 'created_by',
    ];

    protected $casts = [
        'latitude'       => 'decimal:8',
        'longitude'      => 'decimal:8',
        'radius_meters'  => 'integer',
        'is_head_office' => 'boolean',
        'is_active'      => 'boolean',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const GEOFENCE_OFF     = 'off';
    public const GEOFENCE_FLAG    = 'flag';
    public const GEOFENCE_ENFORCE = 'enforce';

    /** NULL disertakan: artinya "ikuti kebijakan global di attendance_settings". */
    public const GEOFENCE_OVERRIDES = [
        self::GEOFENCE_OFF,
        self::GEOFENCE_FLAG,
        self::GEOFENCE_ENFORCE,
    ];

    /** Batas radius yang masuk akal. Di bawah 20 m, galat GPS membuat presensi mustahil. */
    public const RADIUS_MIN = 20;
    public const RADIUS_MAX = 5000;

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    // ── Accessors ───────────────────────────────────────────────────────────

    /** "Jakarta Selatan, DKI Jakarta" — dipakai di daftar dan dropdown. */
    public function getLocationLabelAttribute(): string
    {
        return collect([$this->city, $this->province])->filter()->implode(', ');
    }
}
