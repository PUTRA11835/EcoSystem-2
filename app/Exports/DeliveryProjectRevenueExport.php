<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

/**
 * Export revenue SELURUH Delivery Project (menu "Project Revenue" pada
 * tombol Export Excel di halaman list project):
 *   1. "Project Revenue" — satu baris per project: revenue, plan/actual cost,
 *                          gross profit, dan posisi penagihan TOP
 *                          (invoiced / paid / unbilled / receivable).
 *   2. "Revenue Summary" — rekap per Category, Project Type, dan Customer.
 *
 * Baris disiapkan DeliveryProjectController::exportRevenue().
 *
 * WithPreCalculateFormulas: tanpa ini nilai cache formula (GP, Total, SUBTOTAL)
 * ditulis 0, dan Excel di Protected View menampilkan cache itu apa adanya.
 */
class DeliveryProjectRevenueExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    public function __construct(private array $rows)
    {
    }

    public function sheets(): array
    {
        return [
            new DeliveryProjectRevenueDetailSheet($this->rows),
            new DeliveryProjectRevenueSummarySheet($this->rows),
        ];
    }
}
