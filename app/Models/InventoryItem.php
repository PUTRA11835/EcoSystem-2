<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One line of General Affairs → Inventory & Assets → Inventory: office inventory / consumables kept as a stock
 * quantity. Column meanings: see the create migration.
 */
class InventoryItem extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Inventory';

    protected $table = 'inventory_items';

    protected $fillable = [
        'code', 'name', 'category', 'unit', 'quantity', 'min_stock', 'unit_price', 'location', 'status_override',
        'photo_path', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = ['quantity' => 'integer', 'min_stock' => 'integer', 'unit_price' => 'decimal:2'];

    public const STATUS_INACTIVE = 'inactive';
    public const STATUSES = ['in_stock' => 'In stock', 'low_stock' => 'Low stock', 'out_of_stock' => 'Out of stock', 'inactive' => 'Inactive'];
    public const STATUS_BADGES = [
        'in_stock' => 'bg-green-100 text-green-700', 'low_stock' => 'bg-amber-100 text-amber-700',
        'out_of_stock' => 'bg-red-100 text-red-700', 'inactive' => 'bg-gray-200 text-gray-600',
    ];

    /** The status shown: the one forced by hand, otherwise worked out from the stock. */
    public function status(): string
    {
        if ($this->status_override) {
            return $this->status_override;
        }

        return match (true) {
            $this->quantity <= 0                => 'out_of_stock',
            $this->quantity <= $this->min_stock => 'low_stock',
            default                             => 'in_stock',
        };
    }

    /** SQL mirror of status(): the status forced by hand, otherwise the one the stock gives. */
    public static function statusSql(string $table = 'inventory_items'): string
    {
        return "COALESCE({$table}.status_override, CASE WHEN {$table}.quantity <= 0 THEN 'out_of_stock' WHEN {$table}.quantity <= {$table}.min_stock THEN 'low_stock' ELSE 'in_stock' END)";
    }

    public function stockValue(): float
    {
        return (float) $this->unit_price * $this->quantity;
    }

    public static function money(float|string|null $amount): string
    {
        return 'Rp ' . number_format((float) $amount, 0, ',', '.');
    }
}
