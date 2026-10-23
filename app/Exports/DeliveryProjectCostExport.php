<?php

namespace App\Exports;

use App\Models\DeliveryProject;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;

/**
 * Export Plan Cost satu Delivery Project:
 *   1. "Plan Cost"       — rekap Budget / Release / Actual per cost item,
 *                          sama dengan tabel di section Plan Cost.
 *   2. "Expense Details" — seluruh actual expense detail (termasuk period),
 *                          satu baris per expense.
 *
 * Datanya disiapkan DeliveryProjectCostController::export() dengan helper yang
 * sama dengan tampilan (formatCost/buildSummary), supaya angka di file tidak
 * pernah berbeda dari angka di layar.
 */
class DeliveryProjectCostExport implements WithMultipleSheets, WithPreCalculateFormulas
{
    public function __construct(
        private DeliveryProject $project,
        private array $costs,
        private array $summary,
        private array $expenses,
    ) {
    }

    public function sheets(): array
    {
        return [
            new DeliveryProjectCostRecapSheet($this->project, $this->costs, $this->summary),
            new DeliveryProjectCostExpenseSheet($this->expenses),
        ];
    }
}
