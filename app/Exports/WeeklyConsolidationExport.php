<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Collection;

// Laporan Weekly Consolidation: judul + periode + baris "digenerate" di atas
// tabel (baris 1-3), header tabel di baris 5, data mulai baris 6. Struktur dan
// warna sengaja identik dengan LogShiftingExport (dipakai sebagai referensi
// gaya export di seluruh Reporting) — hanya kolom & isi banner yang berbeda.
// Urutan kolom (Ticket..Notes) mengikuti format tabel yang diminta user.
class WeeklyConsolidationExport implements FromArray, WithStyles, WithColumnWidths
{
    protected Collection $rows;
    protected array $meta;

    private const HEADER_ROW     = 5;
    private const FIRST_DATA_ROW = 6;
    private const LAST_COL       = 'K';

    public function __construct(Collection $rows, array $meta = [])
    {
        $this->rows = $rows;
        $this->meta = $meta;
    }

    public function array(): array
    {
        $blank = array_fill(0, 11, '');

        $rows = [
            array_merge(['WEEKLY CONSOLIDATION — ' . strtoupper($this->meta['module_name'] ?? '')], array_slice($blank, 1)),
            array_merge([$this->periodLabel()], array_slice($blank, 1)),
            array_merge([$this->metaLabel()], array_slice($blank, 1)),
            $blank,
            ['Ticket', 'Description', 'Start Date', 'Type', 'Status', 'Module', 'Lead & Member', 'PIC', 'Progress (%)', 'Deliverable', 'Notes'],
        ];

        foreach ($this->rows as $r) {
            $startDate = $r['start_date'] ?? null;

            $rows[] = [
                $r['ticket_number'] ?? '—',
                $r['description'] ?? '—',
                $startDate ? $startDate->format('d M Y H:i') . ' WIB' : '—',
                $r['ticket_type'] ?? '—',
                $r['status_label'] ?? '—',
                $r['module_name'] ?? '—',
                $r['lead_member'] ?: '—',
                $r['pic'] ?? '—',
                isset($r['progress_percentage']) ? number_format((float) $r['progress_percentage'], 0) . '%' : '—',
                $this->deliverableLabel($r['deliverable_status'] ?? null),
                $r['notes'] ?? '',
            ];
        }

        return $rows;
    }

    private function deliverableLabel(?string $status): string
    {
        return match ($status) {
            'ok'     => 'OK',
            'not_ok' => 'Not OK',
            default  => '—',
        };
    }

    private function periodLabel(): string
    {
        return 'Period: ' . ($this->meta['period_label'] ?? '-');
    }

    private function metaLabel(): string
    {
        $generatedAt = $this->meta['generated_at'] ?? null;
        $generatedBy = $this->meta['generated_by'] ?? 'System';
        $genStr = $generatedAt ? $generatedAt->format('d/m/Y H:i') . ' WIB' : '—';
        return "Generated: {$genStr} by {$generatedBy}  •  Total {$this->rows->count()} Tickets";
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16, // Ticket
            'B' => 34, // Description
            'C' => 18, // Start Date
            'D' => 14, // Type
            'E' => 18, // Status
            'F' => 12, // Module
            'G' => 26, // Lead & Member
            'H' => 16, // PIC
            'I' => 11, // Progress (%)
            'J' => 11, // Deliverable
            'K' => 35, // Notes
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow    = self::HEADER_ROW;
        $firstDataRow = self::FIRST_DATA_ROW;
        $lastCol      = self::LAST_COL;
        $lastRow      = $headerRow + $this->rows->count();

        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getRowDimension(1)->setRowHeight(22);

        $sheet->freezePane("A{$firstDataRow}");
        $sheet->setAutoFilter("A{$headerRow}:{$lastCol}{$lastRow}");

        $thinBorder = ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFE5E7EB']];

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'size' => 16, 'color' => ['argb' => 'FF991B1B']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            2 => [
                'font'      => ['italic' => true, 'size' => 10, 'color' => ['argb' => 'FF4B5563']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            3 => [
                'font'      => ['italic' => true, 'size' => 9, 'color' => ['argb' => 'FF9CA3AF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $headerRow => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFB91C1C']]],
            ],
        ];

        for ($i = $firstDataRow; $i <= $lastRow; $i++) {
            $argb = (($i % 2) === 0) ? 'FFF9FAFB' : 'FFFFFFFF';
            $styles["A{$i}:{$lastCol}{$i}"] = [
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
                'borders'   => ['allBorders' => $thinBorder],
                'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
            ];
            foreach (['C', 'D', 'E', 'F', 'I', 'J'] as $col) {
                $styles["{$col}{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP]];
            }
        }

        return $styles;
    }
}
