<?php

namespace App\Models\PurchaseOrder;

use Illuminate\Database\Eloquent\Model;

class PurchaseOrderItem extends Model
{
    protected $table = 'purchase_order_items';

    protected $fillable = [
        'purchase_order_id', 'line_no', 'description', 'qty', 'unit', 'currency', 'unit_price',
        'project_label', 'period_from', 'period_to',
        'discount_type', 'discount_value', 'tax_keys',
        'line_subtotal', 'discount_amount', 'vat_amount', 'withholding_amount', 'line_total',
    ];

    protected $casts = [
        'qty'                => 'float',
        'unit_price'         => 'float',
        'discount_value'     => 'float',
        'tax_keys'           => 'array',
        'line_subtotal'      => 'float',
        'discount_amount'    => 'float',
        'vat_amount'         => 'float',
        'withholding_amount' => 'float',
        'line_total'         => 'float',
        'period_from'        => 'date',
        'period_to'          => 'date',
    ];

    public function order()
    {
        return $this->belongsTo(PurchaseOrder::class, 'purchase_order_id');
    }

    public function periodLabel(): string
    {
        if (!$this->period_from) {
            return '—';
        }

        $from = $this->period_from->format('d M Y');

        return $this->period_to && !$this->period_to->equalTo($this->period_from)
            ? $from . ' – ' . $this->period_to->format('d M Y')
            : $from;
    }
}
