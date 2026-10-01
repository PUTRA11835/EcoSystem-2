<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * A compensation line an offering letter can carry (base salary, an
 * allowance, ...), maintained in Offering Letter → Settings. Its name is
 * printed on the letter as it is.
 */
class OfferComponent extends Model
{
    protected $table = 'recruitment_offer_components';

    protected $fillable = ['name', 'kind', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order' => 'integer',
        'is_active'  => 'boolean',
    ];

    public const KIND_BASE     = 'base';
    public const KIND_FIXED    = 'fixed';
    public const KIND_VARIABLE = 'variable';

    /** The kinds HR can pick; the base salary row is seeded and stays what it is. */
    public const ALLOWANCE_KINDS = [
        self::KIND_FIXED    => 'Fixed allowance',
        self::KIND_VARIABLE => 'Variable',
    ];

    public function isBase(): bool
    {
        return $this->kind === self::KIND_BASE;
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** True while a saved letter carries this line — such a component is deactivated, not deleted. */
    public function isInUse(): bool
    {
        return Offer::whereJsonContains('compensation', [['component_id' => $this->id]])->exists();
    }
}
