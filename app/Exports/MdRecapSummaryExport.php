<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

/**
 * "Summary" MD Recap export — no Delivery column/grouping. One row per
 * employee + mode (matches the pre-Delivery export shape), for readers who
 * just want totals per person without the delivery-level breakdown.
 * @see MdRecapExport for the Delivery-level counterpart.
 */
class MdRecapSummaryExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn($r) => [
            'name'     => $r['name'],
            'entries'  => $r['entries'],
            'mode'     => $r['mode'],
            'mandays'  => $r['mandays'],
        ]);
    }

    public function headings(): array
    {
        return ['Name', 'Entries', 'Mode', 'Mandays'];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        // Same "bold name + thick top border on first row of each employee's
        // group" convention as MdRecapExport, kept consistent across both
        // export flavors. Name stays repeated on every row (never blanked) so
        // the sheet is safe to filter/sort/pivot.
        $rowsIndexed  = $this->rows->values();
        $previousName = null;

        for ($i = 2; $i <= $lastRow; $i++) {
            $argb = (($i % 2) === 0) ? 'FFE2EFDA' : 'FFFFFFFF';
            $styles["A{$i}:D{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
            ];
            $styles["B{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
            $styles["D{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];

            $currentRow  = $rowsIndexed->get($i - 2);
            $currentName = is_array($currentRow) ? ($currentRow['name'] ?? null) : null;

            if ($currentName !== null && $currentName !== $previousName) {
                $styles["A{$i}"] = ['font' => ['bold' => true]];
                $styles["A{$i}:D{$i}"] = array_merge($styles["A{$i}:D{$i}"], [
                    'borders' => [
                        'top' => ['borderStyle' => Border::BORDER_MEDIUM, 'color' => ['argb' => 'FFCC0000']],
                    ],
                ]);
            }

            $previousName = $currentName;
        }

        return $styles;
    }
}
