<?php

namespace App\Models;

use App\Models\Letters\LetterCode;
use App\Models\Letters\LetterSetting;
use Illuminate\Database\Eloquent\Model;

/**
 * Per letter type (Letterhead::letterTypes()), set in HR & General → Letter
 * Templates → Settings: the language a new letter of that type starts in,
 * whether the type is in use, and the letter code a new letter starts with.
 * Each letter still carries its own language and code, picked when it is
 * written; changing these never changes a letter that already exists. What
 * employees can request is a list of its own (LetterRequestType).
 */
class LetterTypeSetting extends Model
{
    protected $fillable = ['letter_type', 'language', 'is_active', 'letter_code_id'];

    protected $casts = [
        'is_active'      => 'boolean',
    ];

    public const LANGUAGE_INDONESIAN = 'id';
    public const LANGUAGE_ENGLISH    = 'en';

    /** The languages a letter can be printed in — a key here is also the folder name under lang/. */
    public const LANGUAGES = [
        self::LANGUAGE_INDONESIAN => 'Bahasa Indonesia',
        self::LANGUAGE_ENGLISH    => 'English',
    ];

    public function code()
    {
        return $this->belongsTo(LetterCode::class, 'letter_code_id');
    }

    /**
     * What the {lang} token of a letter number prints for $language — IN / EN
     * by default (IN rather than the ISO code ID, because the letters already
     * issued carry IN); set in Letter Templates → Settings.
     */
    public static function numberCode(?string $language): string
    {
        return LetterSetting::current()->languageCode($language);
    }

    /** The settings row of a letter type; an unsaved one with the defaults when there is none. */
    public static function for(string $type): self
    {
        return static::firstOrNew(['letter_type' => $type], ['language' => self::LANGUAGE_INDONESIAN, 'is_active' => true]);
    }

    /** The language a new letter of $type starts in; Indonesian until HR picks another. */
    public static function languageFor(string $type): string
    {
        $language = static::where('letter_type', $type)->value('language');

        return isset(self::LANGUAGES[$language]) ? $language : self::LANGUAGE_INDONESIAN;
    }

    /** @return array<string, string> [letter type => language] for every letter type */
    public static function languages(): array
    {
        $saved = static::pluck('language', 'letter_type');

        return collect(Letterhead::letterTypes())
            ->map(fn ($label, string $type) => isset(self::LANGUAGES[$saved[$type] ?? null]) ? $saved[$type] : self::LANGUAGE_INDONESIAN)
            ->all();
    }
}
