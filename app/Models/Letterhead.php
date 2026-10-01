<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A letterhead — a header and a footer image — maintained in HR & General →
 * Letter Templates, and the letters it is printed on.
 *
 * A letter type is printed on at most one letterhead: ticking it on one
 * letterhead takes it off the others (LetterTemplateController).
 */
class Letterhead extends Model
{
    protected $fillable = ['name', 'header_path', 'footer_path', 'letter_types'];

    protected $casts = ['letter_types' => 'array'];

    public const DISK = 'local';

    public const TYPE_OFFERING_LETTER = 'offering_letter';

    /** Letters that can be printed on a letterhead. A new letter template adds its key here. */
    public const LETTER_TYPES = [
        self::TYPE_OFFERING_LETTER => 'Offering Letter',
    ];

    public const PARTS = ['header', 'footer'];

    /** Width of an A4 page in PDF points; the images are printed edge to edge. */
    private const PAGE_WIDTH_PT = 595.28;

    protected static function booted(): void
    {
        static::deleting(function (self $letterhead) {
            Storage::disk(self::DISK)->delete(array_filter([$letterhead->header_path, $letterhead->footer_path]));
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

    public function pathOf(string $part): ?string
    {
        $path = $this->{"{$part}_path"};

        return $path && Storage::disk(self::DISK)->exists($path) ? $path : null;
    }

    /** The image inlined for the PDF renderer, which cannot reach a private disk by URL. */
    public function dataUri(string $part): ?string
    {
        if (!$path = $this->pathOf($part)) {
            return null;
        }

        $disk = Storage::disk(self::DISK);

        return 'data:' . $disk->mimeType($path) . ';base64,' . base64_encode($disk->get($path));
    }

    /** Height the image takes when printed across the full page width, in PDF points. */
    public function heightPt(string $part): float
    {
        if (!$path = $this->pathOf($part)) {
            return 0;
        }

        [$width, $height] = getimagesize(Storage::disk(self::DISK)->path($path)) ?: [0, 0];

        return $width > 0 ? round(self::PAGE_WIDTH_PT * $height / $width, 2) : 0;
    }
}
