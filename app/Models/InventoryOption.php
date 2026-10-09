<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * One choice of a dropdown in Inventory & Assets (managed in Settings). Column meanings: see the create migration.
 */
class InventoryOption extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Inventory';

    protected $table = 'inventory_options';

    protected $fillable = ['field', 'value', 'label', 'tone', 'sort_order', 'is_active', 'is_system'];

    protected $casts = ['is_active' => 'boolean', 'is_system' => 'boolean', 'sort_order' => 'integer'];

    /** Badge colours a condition / status can take. */
    public const TONES = [
        'gray'  => 'bg-gray-100 text-gray-600',
        'green' => 'bg-green-100 text-green-700',
        'amber' => 'bg-amber-100 text-amber-700',
        'red'   => 'bg-red-100 text-red-700',
        'blue'  => 'tone-primary',
    ];

    /**
     * The dropdowns. `keyed` = the records store a fixed key (the label and colour can change freely); otherwise they
     * store the name itself, so renaming an option renames it on every record too. `columns` = where records hold it.
     */
    public const FIELDS = [
        'item_category'   => ['group' => 'Inventory', 'title' => 'Category', 'keyed' => false, 'columns' => [['inventory_items', 'category']],
            'about' => 'Groups inventory lines in the form, the filters and the Overview.'],
        'item_unit'       => ['group' => 'Inventory', 'title' => 'Unit', 'keyed' => false, 'columns' => [['inventory_items', 'unit']],
            'about' => 'What a quantity is counted in (pcs, box, ream ...).'],
        'asset_category'  => ['group' => 'Assets', 'title' => 'Category', 'keyed' => false, 'columns' => [['inventory_assets', 'category']],
            'about' => 'Groups assets in the form, the filters and the Overview.'],
        'asset_condition' => ['group' => 'Assets', 'title' => 'Condition', 'keyed' => true, 'columns' => [['inventory_assets', 'condition']],
            'about' => 'The physical state of an asset. "Damaged" feeds the Needs attention list, so the three built-in rows can be renamed and recoloured but not removed.'],
        'asset_status'    => ['group' => 'Assets', 'title' => 'Status', 'keyed' => true, 'columns' => [['inventory_assets', 'status']],
            'about' => 'Where an asset stands. "In use" follows the assignee, "Under maintenance" feeds Needs attention and "Disposed" leaves the Overview totals, so the four built-in rows can be renamed and recoloured but not removed.'],
        'location'        => ['group' => 'Both registers', 'title' => 'Location', 'keyed' => false, 'columns' => [['inventory_items', 'location'], ['inventory_assets', 'location']],
            'about' => 'Where things are kept; one list for inventory and assets.'],
    ];

    /** @var array<string, \Illuminate\Support\Collection> per-request cache of each list */
    private static array $cache = [];

    protected static function booted(): void
    {
        static::saved(fn () => self::$cache = []);
        static::deleted(fn () => self::$cache = []);
    }

    /** Every option of a dropdown, in the order Settings shows them. */
    public static function forField(string $field)
    {
        return self::$cache[$field] ??= static::query()->where('field', $field)->orderBy('sort_order')->orderBy('id')->get();
    }

    /** [value => label] of every option, switched off ones included — for showing and filtering what records hold. */
    public static function labels(string $field): array
    {
        return self::forField($field)->pluck('label', 'value')->all();
    }

    /** [value => label] of the options a form offers. */
    public static function choices(string $field): array
    {
        return self::forField($field)->where('is_active', true)->pluck('label', 'value')->all();
    }

    /** The values a form may save: the offered ones, plus the record's current value (a record keeps what it has). */
    public static function allowed(string $field, ?string $current = null): array
    {
        return array_values(array_unique(array_filter([...array_keys(self::choices($field)), $current], fn ($v) => $v !== null && $v !== '')));
    }

    /** Badge classes of a condition / status value. */
    public static function badge(string $field, ?string $value): string
    {
        $tone = self::forField($field)->firstWhere('value', $value)?->tone;

        return self::TONES[$tone] ?? self::TONES['gray'];
    }

    /** [value => number of records using it] across every place the dropdown is stored. */
    public static function usage(string $field): array
    {
        $counts = [];
        foreach (self::FIELDS[$field]['columns'] as [$table, $column]) {
            foreach (DB::table($table)->whereNotNull($column)->select($column, DB::raw('COUNT(*) as n'))->groupBy($column)->pluck('n', $column) as $value => $n) {
                $counts[$value] = ($counts[$value] ?? 0) + $n;
            }
        }

        return $counts;
    }

    /** What the audit log shows for this record. */
    public function getNameAttribute(): string
    {
        return (self::FIELDS[$this->field]['group'] ?? '') . ' · ' . (self::FIELDS[$this->field]['title'] ?? $this->field) . ': ' . $this->label;
    }
}
