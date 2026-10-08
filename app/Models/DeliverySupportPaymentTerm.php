<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

/**
 * Termin TOP Delivery Support — mirror DeliveryProjectPaymentTerm. Status dan
 * basis amount mengikuti versi project (DeliveryProjectPaymentTerm::STATUSES /
 * ::BASES), rumus amount lewat Concerns\HasPaymentTermBasis.
 */
class DeliverySupportPaymentTerm extends Model
{
    use HasFactory, Auditable, Concerns\HasPaymentTermBasis;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_payment_terms';

    /** Open | Invoiced | Paid | Delay — sama dengan Delivery Project. */
    public const STATUSES = DeliveryProjectPaymentTerm::STATUSES;

    /** percentage | line_item | fixed — sama dengan Delivery Project. */
    public const BASES = DeliveryProjectPaymentTerm::BASES;

    protected $fillable = [
        'delivery_support_id',
        'term_number',
        'basis',
        'contract_line_item_id',
        'payment_term',
        'period',
        'payment_percentage',
        'amount',
        'requirements',
        'estimated_date',
        'submit_invoice_date',
        'invoice_number',
        'paid_date',
        'status',
    ];

    protected $casts = [
        'term_number'         => 'integer',
        'payment_percentage'  => 'decimal:2',
        'amount'              => 'decimal:2',
        'estimated_date'      => 'date',
        'submit_invoice_date' => 'date',
        'paid_date'           => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────

    public function support()
    {
        return $this->belongsTo(DeliverySupport::class, 'delivery_support_id');
    }

    public function contractLineItem()
    {
        return $this->belongsTo(DeliverySupportContractLineItem::class, 'contract_line_item_id');
    }
}
