<?php

namespace App\Models\Recruitment;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * A compensation line an offering letter can carry (base salary, an
 * allowance, ...), maintained in Offering Letter → Settings. Its name is
 * printed on the letter as it is — on an English letter, its English name
 * (`name_en`) when it has one.
 */
class OfferComponent extends Model
{
    protected $table = 'recruitment_offer_components';

    protected $fillable = ['name', 'name_en', 'kind', 'default_type', 'default_value', 'sort_order', 'is_active'];

    protected $casts = [
        'sort_order'    => 'integer',
        'is_active'     => 'boolean',
        'default_value' => 'float',
    ];

    public const DEFAULT_AMOUNT  = 'amount';
    public const DEFAULT_PERCENT = 'percent';

    /** What a default can be: a rupiah amount, or a percentage of the letter's base salary. */
    public const DEFAULT_TYPES = [
        self::DEFAULT_AMOUNT  => 'Rp',
        self::DEFAULT_PERCENT => '% of base',
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

    /**
     * Names of the active fixed allowances and variable components — what
     * the note under the base salary percentage lists.
     *
     * @return array{0: string[], 1: string[]}  [fixed, variable]
     */
    public static function activeNamesByKind(Collection $components): array
    {
        $active = $components->where('is_active', true);

        return [
            $active->where('kind', self::KIND_FIXED)->pluck('name')->values()->all(),
            $active->where('kind', self::KIND_VARIABLE)->pluck('name')->values()->all(),
        ];
    }

    /** True while a saved letter or an employee carries this line — such a component is deactivated, not deleted. */
    public function isInUse(): bool
    {
        return Offer::whereJsonContains('compensation', [['component_id' => $this->id]])->exists()
            || \App\Models\EmployeeSalaryComponent::where('component_id', $this->id)->exists();
    }
}
