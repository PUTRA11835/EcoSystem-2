<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliveryProjectPaymentTerm extends Model
{
    use HasFactory, Auditable, Concerns\TouchesProjectActivity;

    protected static ?string $auditModule = 'Delivery Project';

    protected $table = 'delivery_project_payment_terms';

    protected $fillable = [
        'delivery_projects_id',
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

    public function project()
    {
        return $this->belongsTo(DeliveryProject::class, 'delivery_projects_id');
    }

    public function contractLineItem()
    {
        return $this->belongsTo(DeliveryProjectContractLineItem::class, 'contract_line_item_id');
    }

    // ── Amount ─────────────────────────────────────────────────────

    /**
     * Satu-satunya rumus amount termin. Basis "percentage" diturunkan dari
     * revenue Sales Data (nilai tersimpan bisa basi bila revenue berubah);
     * basis "fixed" memakai nominal yang diisi user apa adanya.
     *
     * Static + argumen mentah supaya bisa dipakai juga untuk baris DB::table().
     */
    public static function amountFor(?string $basis, $percentage, $storedAmount, $revenue): float
    {
        if ($basis === 'fixed') {
            return round((float) $storedAmount, 2);
        }

        return round(((float) $revenue) * ((float) $percentage) / 100, 2);
    }

    public function effectiveAmount($revenue): float
    {
        return self::amountFor($this->basis, $this->payment_percentage, $this->amount, $revenue);
    }

    public function isFixed(): bool
    {
        return $this->basis === 'fixed';
    }
}
