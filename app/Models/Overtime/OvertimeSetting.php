<?php

namespace App\Models\Overtime;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi global sub-modul Overtime. Tabel satu baris.
 *
 * Selalu diambil lewat OvertimeSetting::current() supaya kode pemanggil tidak
 * perlu tahu bahwa tabelnya hanya berisi satu baris, dan supaya pembacaannya
 * tidak berulang dalam satu request. Meniru AttendanceSetting yang sudah ada.
 */
class OvertimeSetting extends Model
{
    protected $table = 'overtime_settings';

    protected $fillable = [
        'allow_future_date', 'max_backdate_days',
        'allow_crosses_midnight', 'min_duration_minutes',
        'max_daily_minutes', 'max_weekly_minutes',
        'mismatch_tolerance_minutes', 'require_reason_min_chars',
        'allow_self_approval', 'self_approval_fallback_role_id',
        'allow_approver_adjust_time', 'locked_period_policy',
        'updated_by',
    ];

    protected $casts = [
        'allow_future_date'          => 'boolean',
        'max_backdate_days'          => 'integer',
        'allow_crosses_midnight'     => 'boolean',
        'min_duration_minutes'       => 'integer',
        'max_daily_minutes'          => 'integer',
        'max_weekly_minutes'         => 'integer',
        'mismatch_tolerance_minutes' => 'integer',
        'require_reason_min_chars'   => 'integer',
        'allow_self_approval'        => 'boolean',
        'allow_approver_adjust_time' => 'boolean',
    ];

    // ── Konstanta ───────────────────────────────────────────────────────────

    /** Penguncian periode diabaikan sepenuhnya. */
    public const LOCK_OFF = 'off';

    /** Karyawan tidak bisa; pemegang `general.overtime.manage` tetap bisa. */
    public const LOCK_BLOCK_EMPLOYEE = 'block_employee';

    /** Siapa pun tidak bisa. */
    public const LOCK_BLOCK_ALL = 'block_all';

    public const LOCK_POLICIES = [
        self::LOCK_OFF,
        self::LOCK_BLOCK_EMPLOYEE,
        self::LOCK_BLOCK_ALL,
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
            'locked_period_policy' => self::LOCK_BLOCK_EMPLOYEE,
        ]);
    }

    /** Dipanggil setelah menyimpan perubahan agar pembacaan berikutnya segar. */
    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /** Nilai 0 berarti "tanpa batas", bukan "nol menit". */
    public function hasBackdateLimit(): bool
    {
        return $this->max_backdate_days > 0;
    }

    public function hasDailyLimit(): bool
    {
        return $this->max_daily_minutes > 0;
    }

    public function hasWeeklyLimit(): bool
    {
        return $this->max_weekly_minutes > 0;
    }

    public function hasMinimumDuration(): bool
    {
        return $this->min_duration_minutes > 0;
    }
}
