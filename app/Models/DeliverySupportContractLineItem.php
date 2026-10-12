<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract Line Item Delivery Support — mirror DeliveryProjectContractLineItem.
 * Acuan termin pada mode TOP "line_item". Type, frekuensi, batas periode, dan
 * rumus jadwal/nilai kontrak sama persis dengan versi project
 * (lihat Concerns\HasContractLineItemSchedule).
 */
class DeliverySupportContractLineItem extends Model
{
    use HasFactory, Auditable, Concerns\HasContractLineItemSchedule;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_contract_line_items';

    protected $fillable = [
        'delivery_support_id',
        'name',
        'type',
        'frequency',
        'start_date',
        'end_date',
        'amount',
        'order_sequence',
    ];

    protected $casts = [
        'start_date'     => 'date',
        'end_date'       => 'date',
        'amount'         => 'decimal:2',
        'order_sequence' => 'integer',
    ];

    public function support()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function paymentTerms()
    {
        return $this->hasMany(DeliverySupportPaymentTerm::class, 'contract_line_item_id');
    }
}
