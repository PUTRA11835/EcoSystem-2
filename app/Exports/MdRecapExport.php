<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class MdRecapExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected Collection $rows;
    protected int $month;
    protected int $year;

    public function __construct(Collection $rows, int $month, int $year)
    {
        $this->rows  = $rows;
        $this->month = $month;
        $this->year  = $year;
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn($r) => [
            'name'     => $r['name'],
            'mode'     => $r['mode'],
            'mandays'  => $r['mandays'],
        ]);
    }

    public function headings(): array
    {
        return ['Name', 'Mode', 'Mandays'];
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

        for ($i = 2; $i <= $lastRow; $i++) {
            $argb = (($i % 2) === 0) ? 'FFE2EFDA' : 'FFFFFFFF';
            $styles["A{$i}:C{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
            ];
            $styles["C{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
        }

        return $styles;
    }
}
