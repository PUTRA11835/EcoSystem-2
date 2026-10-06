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
        'offer_number_format', 'offer_number_digits', 'offer_base_salary_min_percent', 'offer_ratio_note',
        'offer_response_days', 'offer_signing_city',
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

    /**
     * The note under the base salary percentage of the offering letter form,
     * until one is saved in Offering Settings. Its {placeholders} are filled
     * in by offerRatioNoteHtml().
     */
    public const DEFAULT_RATIO_NOTE = 'Legal basis: base salary of at least <b>{min_percent}%</b> of base salary + fixed allowances.<br>'
        . 'Fixed allowances counted: {fixed_allowances}. Variable components ({variable_components}) are not part of this ratio.<br>'
        . '<i>PP No. 36 Tahun 2021 tentang Pengupahan, Pasal 41</i>';

    /** What the note can say that follows the settings by itself — placeholder => what it becomes. */
    public const RATIO_NOTE_PLACEHOLDERS = [
        '{min_percent}'         => 'Minimum base salary percentage',
        '{fixed_allowances}'    => 'Active fixed allowances',
        '{variable_components}' => 'Active variable components',
    ];

    /** The only markup the note keeps: what the editor's bold / italic / underline and line breaks make. */
    private const NOTE_TAGS = ['b', 'strong', 'i', 'em', 'u', 'br', 'div', 'p'];

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

    /** The minimum percentage as people write it: 75, 72.5. */
    public function minPercentLabel(): string
    {
        return rtrim(rtrim(number_format((float) $this->offer_base_salary_min_percent, 2, '.', ''), '0'), '.');
    }

    /**
     * The note under the base salary percentage, ready to print as HTML: its
     * markup cleaned and its placeholders filled in (each value escaped).
     *
     * @param  string[]  $fixed     names of the active fixed allowances
     * @param  string[]  $variable  names of the active variable components
     */
    public function offerRatioNoteHtml(array $fixed, array $variable): string
    {
        return strtr(self::cleanNote($this->offer_ratio_note ?: self::DEFAULT_RATIO_NOTE), [
            '{min_percent}'         => e($this->minPercentLabel()),
            '{fixed_allowances}'    => e(implode(', ', $fixed) ?: 'none'),
            '{variable_components}' => e(implode(', ', $variable) ?: 'none'),
        ]);
    }

    /** Keeps only the note's own tags, without any attribute, so what is saved can be printed as HTML. */
    public static function cleanNote(?string $html): string
    {
        $tags = implode('|', self::NOTE_TAGS);
        $html = strip_tags((string) $html, self::NOTE_TAGS);

        return trim(preg_replace("#<(/?)({$tags})\b[^>]*>#i", '<$1$2>', $html));
    }
}
