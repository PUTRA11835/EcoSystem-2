<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliverySupportPaymentTerm extends Model
{
    use HasFactory, Auditable;

    protected static ?string $auditModule = 'Delivery Support';

    protected $table = 'delivery_support_payment_terms';

    protected $fillable = [
        'delivery_support_id',
        'term_number',
        'payment_term',
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
}
