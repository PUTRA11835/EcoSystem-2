<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Model;

/**
 * Konfigurasi global sub-modul Rekrutmen. Tabel satu baris.
 *
 * Selalu diambil lewat RecruitmentSetting::current(), meniru
 * ReimbursementSetting — supaya kode pemanggil tidak perlu tahu bahwa
 * tabelnya hanya berisi satu baris.
 */
class RecruitmentSetting extends Model
{
    protected $table = 'recruitment_settings';

    protected $fillable = [
        'default_timezone', 'calendar_provider', 'organizer_email',
        'offer_number_format', 'offer_number_digits', 'offer_base_salary_min_percent', 'offer_legal_basis',
        'offer_default_benefits', 'offer_response_days', 'offer_signing_city',
    ];

    protected $casts = [
        'offer_number_digits'           => 'integer',
        'offer_base_salary_min_percent' => 'float',
        'offer_response_days'           => 'integer',
    ];

    /** Timezones offered on the Settings page — Indonesia's three zones. */
    public const TIMEZONES = [
        'Asia/Jakarta'  => 'WIB — Asia/Jakarta (UTC+7)',
        'Asia/Makassar' => 'WITA — Asia/Makassar (UTC+8)',
        'Asia/Jayapura' => 'WIT — Asia/Jayapura (UTC+9)',
    ];

    /** Cache per-request; bukan Cache facade, supaya perubahan langsung terasa. */
    private static ?self $cached = null;

    public static function current(): self
    {
        if (self::$cached) {
            return self::$cached;
        }

        // refresh(): the offering-letter settings get their values from the column defaults.
        return self::$cached = self::first() ?? self::create([
            'default_timezone'         => 'Asia/Jakarta',
            'calendar_provider'        => 'internal',
        ])->refresh();
    }

    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    /** Mailbox organizer efektif untuk event Graph — override, atau akun bersama. */
    public function effectiveOrganizerEmail(): ?string
    {
        return $this->organizer_email ?: config('services.microsoft_graph.sender_email');
    }
}
