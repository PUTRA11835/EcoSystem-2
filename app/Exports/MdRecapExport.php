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

class MdRecapExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected Collection $rows;
    protected int $month;
    protected int $year;

    public function __construct(Collection $rows, int $month = 0, int $year = 0)
    {
        $this->rows  = $rows;
        $this->month = $month;
        $this->year  = $year;
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn($r) => [
            'name'     => $r['name'],
            'delivery' => $r['delivery'] ?? 'Unassigned',
            'entries'  => $r['entries'],
            'mode'     => $r['mode'],
            'mandays'  => $r['mandays'],
        ]);
    }

    public function headings(): array
    {
        return ['Name', 'Delivery', 'Entries', 'Mode', 'Mandays'];
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

        // Name is deliberately repeated on every row (not blanked/merged) so the
        // file stays safe to filter/sort/pivot — a blanked name on repeat rows
        // breaks that the moment someone sorts or filters the sheet. Instead,
        // the FIRST row of each employee's group gets a bold name + a thicker
        // top border, so groups are still easy to spot at a glance without
        // sacrificing the data's integrity.
        $rowsIndexed  = $this->rows->values();
        $previousName = null;

        for ($i = 2; $i <= $lastRow; $i++) {
            $argb = (($i % 2) === 0) ? 'FFE2EFDA' : 'FFFFFFFF';
            $styles["A{$i}:E{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
            ];
            $styles["E{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];

            $currentRow  = $rowsIndexed->get($i - 2);
            $currentName = is_array($currentRow) ? ($currentRow['name'] ?? null) : null;

            if ($currentName !== null && $currentName !== $previousName) {
                $styles["A{$i}"] = ['font' => ['bold' => true]];
                $styles["A{$i}:E{$i}"] = array_merge($styles["A{$i}:E{$i}"], [
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
