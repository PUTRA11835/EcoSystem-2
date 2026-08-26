<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi global modul Attendance. Tabel satu baris.
 *
 * Selalu diambil lewat AttendanceSetting::current() supaya kode pemanggil
 * tidak perlu tahu bahwa tabelnya hanya berisi satu baris, dan supaya
 * pembacaannya tidak berulang dalam satu request.
 */
class AttendanceSetting extends Model
{
    protected $table = 'attendance_settings';

    protected $fillable = [
        'geofence_mode', 'min_accuracy_meters', 'require_location',
        'attendance_source', 'max_shifts_per_employee',
        'default_check_in', 'default_check_out', 'default_late_tolerance_minutes',
        'allow_self_correction', 'correction_max_days',
        'auto_close_hours', 'updated_by',
    ];

    protected $casts = [
        'min_accuracy_meters'            => 'integer',
        'require_location'               => 'boolean',
        'max_shifts_per_employee'        => 'integer',
        'default_late_tolerance_minutes' => 'integer',
        'allow_self_correction'          => 'boolean',
        'correction_max_days'            => 'integer',
        'auto_close_hours'               => 'integer',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    public const GEOFENCE_OFF     = 'off';
    public const GEOFENCE_FLAG    = 'flag';
    public const GEOFENCE_ENFORCE = 'enforce';

    public const GEOFENCE_MODES = [
        self::GEOFENCE_OFF,
        self::GEOFENCE_FLAG,
        self::GEOFENCE_ENFORCE,
    ];

    public const SOURCE_ESS         = 'ess_login';
    public const SOURCE_FINGERPRINT = 'fingerprint_excel';

    public const SOURCES = [
        self::SOURCE_ESS,
        self::SOURCE_FINGERPRINT,
    ];

    /** Cache per-request; bukan Cache facade, supaya perubahan langsung terasa. */
    private static ?self $cached = null;

    /**
     * Baris konfigurasi yang berlaku.
     *
     * Membuatkan baris default bila belum ada. Ini pengaman: migrasi sudah
     * menyisipkannya, tetapi database yang dipulihkan dari dump lama bisa saja
     * tidak punya, dan seluruh modul akan mati bila mengembalikan null.
     */
    public static function current(): self
    {
        if (self::$cached) {
            return self::$cached;
        }

        return self::$cached = self::first() ?? self::create([
            'geofence_mode'      => self::GEOFENCE_FLAG,
            'attendance_source'  => self::SOURCE_ESS,
        ]);
    }

    /** Dipanggil setelah menyimpan perubahan agar pembacaan berikutnya segar. */
    public static function forgetCache(): void
    {
        self::$cached = null;
    }
}
