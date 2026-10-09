<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Model;

/**
 * One tracked company asset (General Affairs → Inventory & Assets → Assets): one row per unit.
 * Column meanings: see the create migration.
 */
class InventoryAsset extends Model
{
    use Auditable;

    protected static ?string $auditModule = 'Inventory';

    protected $table = 'inventory_assets';

    protected $fillable = [
        'code', 'name', 'category', 'brand', 'serial_number', 'assignee_employee_id', 'purchase_date', 'purchase_price',
        'condition', 'status', 'location', 'photo_path', 'notes', 'created_by', 'updated_by',
    ];

    protected $casts = ['purchase_date' => 'date', 'purchase_price' => 'decimal:2'];

    /** Condition and status are dropdowns managed in Settings (InventoryOption); this is the key the Overview totals rely on. */
    public const STATUS_DISPOSED = 'disposed';
}
