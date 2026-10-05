<?php

namespace App\Models\Letters;

use App\Models\LetterTypeSetting;
use Illuminate\Database\Eloquent\Model;

/**
 * The one row of Letter Templates → Settings: the company the letters are
 * written for, the city they are signed in, the number formats of outgoing
 * letters and of the incoming agenda, and the IN / EN segment ({lang}) a
 * number prints per language.
 */
class LetterSetting extends Model
{
    protected $table = 'letter_settings';

    protected $fillable = [
        'company_name', 'signing_city',
        'outgoing_number_format', 'outgoing_number_digits',
        'incoming_agenda_format', 'incoming_agenda_digits',
        'language_codes', 'allow_other_requests',
    ];

    protected $casts = [
        'language_codes'          => 'array',
        'allow_other_requests'    => 'boolean',
        'outgoing_number_digits'  => 'integer',
        'incoming_agenda_digits'  => 'integer',
    ];

    /** The tokens a number format can use, with what each prints. */
    public const TOKENS = [
        '{seq}'   => 'running number, restarts every year',
        '{code}'  => 'letter code (OF, FI, SM…)',
        '{lang}'  => 'language segment (IN / EN)',
        '{day}'   => 'day of the letter date',
        '{month}' => 'month, 2 digits',
        '{roman}' => 'month in Roman numerals',
        '{year}'  => 'year, 4 digits',
        '{yy}'    => 'year, 2 digits',
    ];

    private const DEFAULT_LANGUAGE_CODES = [
        LetterTypeSetting::LANGUAGE_INDONESIAN => 'IN',
        LetterTypeSetting::LANGUAGE_ENGLISH    => 'EN',
    ];

    private static ?self $cached = null;

    public static function current(): self
    {
        return self::$cached ??= static::query()->first() ?? static::create(['language_codes' => self::DEFAULT_LANGUAGE_CODES])->refresh();
    }

    public static function forgetCache(): void
    {
        self::$cached = null;
    }

    /** What {lang} prints for $language: IN for Indonesian, EN for English, as set in Settings. */
    public function languageCode(?string $language): string
    {
        $codes = array_merge(self::DEFAULT_LANGUAGE_CODES, array_filter((array) $this->language_codes));

        return $codes[$language] ?? $codes[LetterTypeSetting::LANGUAGE_INDONESIAN];
    }
}
