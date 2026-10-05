<?php

namespace App\Models\Letters;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A letter code printed in a letter number — the {code} token: OF, FI, SM,
 * OL (offering letter)… Maintained in Letter Templates → Settings. A code a
 * letter already carries can be deactivated but not deleted, so old numbers
 * keep their meaning.
 */
class LetterCode extends Model
{
    protected $table = 'letter_codes';

    protected $fillable = ['code', 'name', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('code');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function label(): string
    {
        return $this->name ? "{$this->code} — {$this->name}" : $this->code;
    }

    public function isInUse(): bool
    {
        return Letter::withTrashed()->where('letter_code_id', $this->id)->exists()
            || \App\Models\LetterTypeSetting::where('letter_code_id', $this->id)->exists();
    }
}
