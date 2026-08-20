<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliverySupportCost extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_costs';

    protected $fillable = [
        'delivery_support_id',
        'parent_id',
        'code',
        'name',
        'cost_type',
        'budget',
        'release_amount',
        'actual_amount',
        'order_sequence',
    ];

    protected $casts = [
        'budget'         => 'float',
        'release_amount' => 'float',
        'actual_amount'  => 'float',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function support()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function parent()
    {
        return $this->belongsTo(DeliverySupportCost::class, 'parent_id');
    }

    public function children()
    {
        return $this->hasMany(DeliverySupportCost::class, 'parent_id')
                    ->orderBy('order_sequence');
    }

    public function items()
    {
        return $this->hasMany(DeliverySupportCostItem::class, 'delivery_support_cost_id')
                    ->orderBy('created_at');
    }

    // ── Computed helpers ──────────────────────────────────────────

    public function getAvailableBudgetAttribute(): ?float
    {
        if ($this->budget === null && $this->release_amount === null) {
            return null;
        }
        return ($this->budget ?? 0) - ($this->release_amount ?? 0);
    }

    public function getAvailableReleaseAttribute(): ?float
    {
        if ($this->release_amount === null && $this->actual_amount === null) {
            return null;
        }
        return ($this->release_amount ?? 0) - ($this->actual_amount ?? 0);
    }

    // ── Aggregated totals (for parent rows) ──────────────────────

    public function totalBudget(): float
    {
        if ($this->children()->exists()) {
            return $this->children()->sum('budget');
        }
        return (float) ($this->budget ?? 0);
    }

    public function totalRelease(): float
    {
        if ($this->children()->exists()) {
            return $this->children()->sum('release_amount');
        }
        return (float) ($this->release_amount ?? 0);
    }

    public function totalActual(): float
    {
        if ($this->children()->exists()) {
            return $this->children()->sum('actual_amount');
        }
        return (float) ($this->actual_amount ?? 0);
    }
}
