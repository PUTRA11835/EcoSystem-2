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

// Laporan Log Shifting: judul + periode + baris "digenerate" di atas tabel (baris 1-3),
// header tabel di baris 5, data mulai baris 6. FromArray dipakai (bukan
// FromCollection+WithHeadings) supaya baris judul/periode bisa disisipkan sebelum header.
class LogShiftingExport implements FromArray, WithStyles, WithColumnWidths
{
    protected Collection $rows;
    protected array $meta;

    private const HEADER_ROW     = 5;
    private const FIRST_DATA_ROW = 6;
    private const LAST_COL       = 'F';

    public function __construct(Collection $rows, array $meta = [])
    {
        $this->rows = $rows;
        $this->meta = $meta;
    }

    public function array(): array
    {
        $rows = [
            ['LOG SHIFTING REPORT', '', '', '', '', ''],
            [$this->periodLabel(), '', '', '', '', ''],
            [$this->metaLabel(), '', '', '', '', ''],
            ['', '', '', '', '', ''],
            ['No Tiket', 'Deskripsi', 'Tanggal', 'Jam', 'SLA Note', 'PIC'],
        ];

        foreach ($this->rows as $r) {
            $bubble = $r['bubble_date'] ?? null;
            $rows[] = [
                $r['ticket_number'] ?? '—',
                $r['description'] ?? '—',
                $bubble ? $bubble->format('d/m/Y') : '—',
                $bubble ? $bubble->format('H:i') : '—',
                $r['sla_message'] ?? '',
                $r['pic'] ?? 'Unknown',
            ];
        }

        return $rows;
    }

    private function periodLabel(): string
    {
        $from = $this->meta['from'] ?? null;
        $to   = $this->meta['to'] ?? null;
        if (!$from || !$to) {
            return '';
        }
        return 'Periode: ' . $from->format('d/m/Y H:i') . ' s/d ' . $to->format('d/m/Y H:i') . ' WIB';
    }

    private function metaLabel(): string
    {
        $generatedAt = $this->meta['generated_at'] ?? null;
        $generatedBy = $this->meta['generated_by'] ?? 'System';
        $genStr = $generatedAt ? $generatedAt->format('d/m/Y H:i') . ' WIB' : '—';
        return "Digenerate: {$genStr} oleh {$generatedBy}  •  Total {$this->rows->count()} SLA Note";
    }

    public function columnWidths(): array
    {
        return [
            'A' => 16,
            'B' => 30,
            'C' => 13,
            'D' => 9,
            'E' => 55,
            'F' => 20,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $headerRow    = self::HEADER_ROW;
        $firstDataRow = self::FIRST_DATA_ROW;
        $lastCol      = self::LAST_COL;
        $lastRow      = $headerRow + $this->rows->count();

        // Banner judul & periode di atas tabel.
        $sheet->mergeCells("A1:{$lastCol}1");
        $sheet->mergeCells("A2:{$lastCol}2");
        $sheet->mergeCells("A3:{$lastCol}3");
        $sheet->getRowDimension(1)->setRowHeight(22);

        // Header + banner selalu terlihat walau data di-scroll ke bawah.
        $sheet->freezePane("A{$firstDataRow}");

        // Dropdown filter Excel di baris header, mencakup seluruh baris data.
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
            foreach (['C', 'D'] as $col) {
                $styles["{$col}{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_TOP]];
            }
        }

        return $styles;
    }
}
