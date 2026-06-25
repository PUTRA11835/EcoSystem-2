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

class ResolutionDaysExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn($r) => [
            'ticket_number'      => $r['ticket_number'],
            'employee_eci'       => $r['employee_eci'],
            'name'               => $r['name'],
            'resolution_days'    => $r['resolution_days'],
            'additional_days'    => $r['additional_days'],
            'note'               => $r['note'],
            'approve_add'        => $r['approve_add'],
            'total'              => $r['total'],
        ]);
    }

    public function headings(): array
    {
        return ['Ticket Number', 'Employee ECI', 'Name', 'Resolution Days', 'Additional Days', 'Note', 'Approve Add', 'Total'];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;
        $lastCol = 'H';

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        for ($i = 2; $i <= $lastRow; $i++) {
            $argb = (($i % 2) === 0) ? 'FFE2EFDA' : 'FFFFFFFF';
            $styles["A{$i}:{$lastCol}{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
            ];
            foreach (['D', 'E', 'G', 'H'] as $col) {
                $styles["{$col}{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
            }
        }

        return $styles;
    }
}
