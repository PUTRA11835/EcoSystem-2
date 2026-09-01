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

class EmployeeExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    // Column count: A–X (24 columns)
    private const LAST_COL = 'X';

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            'ECI',
            'Full Name',
            'Nick Name',
            'Title',
            'Gender',
            'Religion',
            'Marital Status',
            'Birth Date',
            'Birth Place',
            'Personnel Area',
            'Personnel Subarea',
            'Employee Group',
            'Employee Subgroup',
            'Position',
            'Division',
            'Department',
            'Authorization Group',
            'Current Assignment',
            'Direct Supervision',
            'Manager',
            'Home Base',
            'Module',
            'Since Date',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;
        $lastCol = self::LAST_COL;

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];

        for ($i = 2; $i <= $lastRow; $i++) {
            $rowArgb = ($i % 2 === 0) ? 'FFF9FAFB' : 'FFFFFFFF';
            $styles["A{$i}:{$lastCol}{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowArgb]],
            ];
        }

        return $styles;
    }
}
