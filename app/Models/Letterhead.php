<?php

namespace App\Models;

use App\Support\Letters\LetterTemplates;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A letterhead — one full-page A4 background image (logo, contact details,
 * footer, decoration) — maintained in HR & General → Letter Templates, and
 * the letters it is printed on. The letter's text is printed on top of it,
 * inside the page margins of the letter's own PDF template.
 *
 * A letter type is printed on at most one letterhead: ticking it on one
 * letterhead takes it off the others (LetterTemplateController).
 */
class Letterhead extends Model
{
    protected $fillable = ['name', 'background_path', 'letter_types'];

    protected $casts = ['letter_types' => 'array'];

    public const DISK = 'local';

    public const TYPE_OFFERING_LETTER = 'offering_letter';

    /** Kontrak kerja (menu Contract, HC-D66): kop suratnya dipasang di sini seperti surat lain. */
    public const TYPE_EMPLOYMENT_CONTRACT = 'employment_contract';

    /**
     * Letters that can be printed on a letterhead: the offering letter, the employment contract, every
     * template of App\Support\Letters\LetterTemplates and the custom letter.
     *
     * @return array<string, string> [letter type => label]
     */
    public static function letterTypes(): array
    {
        return [self::TYPE_OFFERING_LETTER => 'Offering Letter', self::TYPE_EMPLOYMENT_CONTRACT => 'Employment Contract'] + LetterTemplates::letterTypes();
    }

    protected static function booted(): void
    {
        static::deleting(function (self $letterhead) {
            if ($letterhead->background_path) {
                Storage::disk(self::DISK)->delete($letterhead->background_path);
            }
        });
    }

    public static function forLetter(string $type): ?self
    {
        return static::whereJsonContains('letter_types', $type)->first();
    }

    public function usedFor(string $type): bool
    {
        return in_array($type, $this->letter_types ?? [], true);
    }

    public function backgroundPath(): ?string
    {
        $path = $this->background_path;

        return $path && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /** The image inlined for the PDF renderer, which cannot reach a private disk by URL. */
    public function backgroundDataUri(): ?string
    {
        if (!$path = $this->backgroundPath()) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        return 'data:' . $disk->mimeType($path) . ';base64,' . base64_encode($disk->get($path));
    }
}
