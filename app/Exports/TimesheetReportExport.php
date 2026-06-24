<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class TimesheetReportExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    protected Collection $rows;
    protected int $periodYear;
    protected int $periodMonth;

    public function __construct(Collection $rows, int $periodYear = 0, int $periodMonth = 0)
    {
        $this->rows         = $rows;
        $this->periodYear   = $periodYear;
        $this->periodMonth  = $periodMonth;
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($row) {
            return [
                'tiket'          => $row['ticket_number'] ?? '-',
                'nama'           => $row['employee_name'] ?? '-',
                'bulan'          => $row['period_month'],
                'tahun'          => $row['period_year'],
                'quota_mandays'  => $row['jatah_md'],
                'realisasi_md'   => $row['md_consumed'],
                'status'         => $row['status'] ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return ['Tiket', 'Nama', 'Bulan', 'Tahun', 'Quota RD', 'Realisasi RD', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1; // +1 for header

        // Header row styling (row 1)
        $headerStyle = [
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
        ];

        $styles = [
            1 => $headerStyle,
        ];

        // Row-by-row: alternating background + status color
        for ($i = 2; $i <= $lastRow; $i++) {
            $rowIndex = $i - 2; // 0-based
            $row = $this->rows->values()->get($rowIndex);
            $status = $row['status'] ?? null;

            // Status cell color (column G = 7)
            $statusArgb = match ($status) {
                'Match' => 'FF92D050', // green
                'Less'  => 'FFFFFF00', // yellow
                'Over'  => 'FFFF0000', // red
                default => 'FFFFFFFF',
            };

            $styles["G{$i}"] = [
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $statusArgb]],
                'font'      => ['bold' => true],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ];

            // Alternating row fill for columns A-F
            $rowArgb = ($rowIndex % 2 === 0) ? 'FFE2EFDA' : 'FFFFFFFF'; // light green / white
            $styles["A{$i}:F{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowArgb]],
            ];

            // Center-align Bulan + Tahun columns (C, D)
            $styles["C{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
            $styles["D{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
        }

        return $styles;
    }
}
