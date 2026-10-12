<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class DeliveryProjectPaymentTerm extends Model
{
    use HasFactory, Auditable, Concerns\TouchesProjectActivity, Concerns\HasPaymentTermBasis;

    protected static ?string $auditModule = 'Delivery Project';

    protected $table = 'delivery_project_payment_terms';

    /**
     * Status penagihan. "Invoiced" = invoice sudah dikirim, menunggu pembayaran
     * (wajib punya Submit Invoice Date).
     */
    public const STATUSES = ['Open', 'Invoiced', 'Paid', 'Delay'];

    /**
     * Basis amount:
     *   percentage → % × revenue Sales Data (mode "% of Revenue").
     *   line_item  → % × nilai kontrak line item (mode Contract Line Item).
     *   fixed      → nominal diisi user (mode Contract Line Item).
     */
    public const BASES = ['percentage', 'line_item', 'fixed'];

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
     * revenue Sales Data (nilai tersimpan bisa basi bila revenue berubah).
     * Basis "fixed" dan "line_item" memakai amount tersimpan: "line_item"
     * disinkronkan ke nilai kontrak line item setiap kali line item / termin
     * berubah (ProjectTopPlan::resyncLineItemAmounts), sehingga laporan yang
     * membaca baris DB::table() tidak perlu menghitung ulang jadwal line item.
     *
     * Static + argumen mentah supaya bisa dipakai juga untuk baris DB::table().
     */
    public static function amountFor(?string $basis, $percentage, $storedAmount, $revenue): float
    {
        if ($basis === 'fixed' || $basis === 'line_item') {
            return round((float) $storedAmount, 2);
        }

        return round(((float) $revenue) * ((float) $percentage) / 100, 2);
    }

    /** Amount basis "line_item" = % × nilai kontrak line item. */
    public static function lineItemAmount($percentage, $lineItemTotal): float
    {
        return round(((float) $lineItemTotal) * ((float) $percentage) / 100, 2);
    }

    // effectiveAmount() / isFixed() / isLineItemShare() / isRevenueShare()
    // → Concerns\HasPaymentTermBasis (dipakai juga oleh Delivery Support).
}
