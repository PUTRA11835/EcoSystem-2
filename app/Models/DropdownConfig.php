<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A dropdown "type" (e.g. Position, Division, Personnel Area) in the
 * generic Employee Information dropdown master data system. Its values
 * live in DropdownConfigValue. See migration
 * create_dropdown_configs_tables for the full design rationale.
 */
class DropdownConfig extends Model
{
    protected $fillable = ['code', 'name', 'description', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function values()
    {
        return $this->hasMany(DropdownConfigValue::class);
    }

    /**
     * Active values (in display order) for a given config code — the list
     * used to render a dropdown. Returns [] if the config doesn't exist or
     * is itself inactive.
     *
     * @return string[]
     */
    public static function optionsFor(string $code): array
    {
        $config = static::where('code', $code)->where('is_active', true)->first();

        return $config?->activeValues() ?? [];
    }

    /**
     * @return string[]
     */
    public function activeValues(): array
    {
        return $this->values()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('value')
            ->pluck('value')
            ->all();
    }

    /**
     * [code => is_active] for the given codes — used to decide whether to
     * render a field's block at all on the Employee form (not just whether
     * it has options). A code with no matching row is treated as active
     * (fail open, so a not-yet-seeded/renamed config never silently hides
     * a field).
     *
     * @param  string[]  $codes
     * @return array<string, bool>
     */
    public static function activeMap(array $codes): array
    {
        $found = static::whereIn('code', $codes)->pluck('is_active', 'code');

        $map = [];
        foreach ($codes as $code) {
            $map[$code] = $found->has($code) ? (bool) $found->get($code) : true;
        }

        return $map;
    }
}
