<?php

namespace App\Models\Letters;

use App\Models\LetterTypeSetting;
use App\Support\Letters\LetterTemplates;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A letter employees can choose in My Letter Requests — maintained in Letter
 * Templates → Settings. Linked to a template, HR answers it with that
 * template pre-filled; without one, with a custom letter titled with its name.
 * A request keeps the name it was asked under, so renaming or deleting an
 * option never changes a request already made.
 */
class LetterRequestType extends Model
{
    protected $table = 'letter_request_types';

    protected $fillable = ['name', 'template_key', 'is_active', 'sort_order'];

    protected $casts = ['is_active' => 'boolean'];

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The language a request for it starts in: its template's default, else the custom letter's. */
    public function defaultLanguage(): string
    {
        return LetterTypeSetting::languageFor(LetterTemplates::exists($this->template_key) ? $this->template_key : LetterTemplates::CUSTOM);
    }
}
