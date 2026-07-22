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

/** Mirrors the on-screen Office timesheet table columns. */
class TimesheetOfficeExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    private const LAST_COL = 'I';

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($ts) {
            $employeeName = trim(($ts->employee?->basicData?->first_name ?? '') . ' ' . ($ts->employee?->basicData?->last_name ?? ''));

            return [
                'employee'    => $employeeName ?: '-',
                'date'        => $ts->date?->format('d M Y') ?? '-',
                'start_time'  => $ts->start_time ?? '-',
                'end_time'    => $ts->end_time   ?? '-',
                'duration'    => $ts->duration_minutes ? round($ts->duration_minutes / 60, 2) : '-',
                'presence'    => $ts->presence ? ucfirst($ts->presence) : '-',
                'location'    => $ts->location ?? '-',
                'description' => $ts->description ?? '-',
                'status'      => ucfirst($ts->status ?? '-'),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee',
            'Date',
            'Start Time',
            'End Time',
            'Duration (hrs)',
            'Presence',
            'Location',
            'Description',
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
