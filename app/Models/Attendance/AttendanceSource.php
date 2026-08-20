<?php

namespace App\Models\Attendance;

use Illuminate\Database\Eloquent\Model;

/**
 * Sumber pencatatan presensi (ESS Login, Fingerprint, dan yang menyusul).
 *
 * Beberapa sumber boleh aktif bersamaan. Tepat satu di antaranya ditandai
 * `is_web_checkin` — itulah yang tercatat ketika karyawan menekan tombol di
 * halaman My Attendance.
 */
class AttendanceSource extends Model
{
    protected $table = 'attendance_sources';

    protected $fillable = [
        'code', 'name', 'description',
        'is_active', 'is_web_checkin', 'is_builtin', 'sort_order',
    ];

    protected $casts = [
        'is_active'      => 'boolean',
        'is_web_checkin' => 'boolean',
        'is_builtin'     => 'boolean',
        'sort_order'     => 'integer',
    ];

    /** Kode bawaan yang tidak boleh diubah karena sudah dirujuk riwayat presensi. */
    public const CODE_ESS         = 'ess_login';
    public const CODE_FINGERPRINT = 'fingerprint_excel';

    // ── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    // ── Helpers ─────────────────────────────────────────────────────────────

    /**
     * Sumber yang dicatat saat presensi lewat halaman web.
     *
     * Selalu mengembalikan sesuatu: bila penandanya hilang — misalnya sumber
     * penandanya dinonaktifkan — jatuh ke ESS Login, karena halaman web memang
     * jalur itu. Mengembalikan null akan membuat presensi gagal tersimpan
     * hanya karena persoalan penamaan.
     */
    public static function webCheckinCode(): string
    {
        return self::where('is_web_checkin', true)->value('code') ?? self::CODE_ESS;
    }

    /** Label ramah untuk sebuah kode, dipakai badge di rekap dan riwayat. */
    public static function labelFor(?string $code): string
    {
        if (!$code) {
            return '—';
        }

        return self::where('code', $code)->value('name')
            ?? ucwords(str_replace('_', ' ', $code));
    }
}
