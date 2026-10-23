<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

/**
 * Export global Term Of Payment yang sudah ber-Invoice No, dari SELURUH
 * Delivery Project (tombol Export di halaman list project):
 *   1. "Invoiced TOP"        — satu baris per termin ber-invoice.
 *   2. "Summary by Project"  — total invoiced / paid / unpaid per project,
 *                              plus ringkasan per status.
 *
 * Baris disiapkan DeliveryProjectPaymentTermController::exportInvoiced()
 * memakai ProjectTopPlan::formatTerm(), jadi amount sama dengan di layar.
 *
 * WithPreCalculateFormulas: tanpa ini nilai cache formula (Total, SUBTOTAL)
 * ditulis 0, dan Excel di Protected View (file hasil download) menampilkan
 * cache itu apa adanya → Total terbaca 0.
 */
class DeliveryInvoicedTopExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    public function __construct(private array $rows)
    {
    }

    public function sheets(): array
    {
        return [
            new DeliveryInvoicedTopDetailSheet($this->rows),
            new DeliveryInvoicedTopSummarySheet($this->rows),
        ];
    }
}
