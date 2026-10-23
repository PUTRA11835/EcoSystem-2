<?php

namespace App\Exports;

use App\Models\DeliveryProject;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Plan Cost" — rekap per cost item (lihat DeliveryProjectCostExport).
 *
 * Sel ditulis manual di AfterSheet (bukan FromArray): teks bebas dari user
 * (nama item, nama project) ditulis sebagai string eksplisit sehingga nilai
 * yang diawali "=" tidak pernah dieksekusi Excel sebagai formula.
 */
class DeliveryProjectCostRecapSheet implements WithTitle, WithEvents
{
    private const HEADER_ROW = 7;

    /** Budget, Release, Actual, Avail. Budget, Avail. Release. */
    private const MONEY_COLS = ['C', 'D', 'E', 'F', 'G'];

    public function __construct(
        private DeliveryProject $project,
        private array $costs,
        private array $summary,
    ) {
    }

    public function title(): string
    {
        return 'Plan Cost';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $text  = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);

                // ── Judul + info project ──────────────────────────────
                $text('A1', 'PLAN COST RECAPITULATION');
                $sheet->mergeCells('A1:G1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $info = [
                    'Project'     => $this->project->name,
                    'IO Number'   => $this->project->io_number ?: '-',
                    'Customer'    => $this->project->client->basicData->name_1 ?? '-',
                    'Exported At' => now()->format('d M Y H:i'),
                ];
                $row = 2;
                foreach ($info as $label => $value) {
                    $text("A{$row}", $label);
                    $text("B{$row}", $value);
                    $sheet->mergeCells("B{$row}:G{$row}");
                    $row++;
                }
                $sheet->getStyle('A2:A5')->getFont()->setBold(true);

                // ── Header tabel ──────────────────────────────────────
                $headerRow = self::HEADER_ROW;
                foreach (['Code', 'Item Name', 'Budget', 'Release', 'Actual', 'Avail. Budget', 'Avail. Release'] as $i => $label) {
                    $text(chr(65 + $i) . $headerRow, $label);
                }
                $sheet->getStyle("A{$headerRow}:G{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);

                // ── Baris cost item (parent lalu sub-item) ────────────
                $row = $headerRow + 1;
                $writeCost = function (array $cost, int $depth) use (&$writeCost, &$row, $sheet, $text) {
                    $text("A{$row}", $cost['code']);
                    $text("B{$row}", $cost['name']);
                    $this->writeMoney($sheet, $row, [
                        $cost['display_budget'],
                        $cost['display_release'],
                        $cost['display_actual'],
                        $cost['avail_budget'],
                        $cost['avail_release'],
                    ]);

                    if ($depth === 0) {
                        $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                            'font' => ['bold' => true],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF3F4F6']],
                        ]);
                    } else {
                        $sheet->getStyle("B{$row}")->getAlignment()->setIndent($depth * 2);
                    }
                    $row++;

                    foreach ($cost['children'] as $child) {
                        $writeCost($child, $depth + 1);
                    }
                };
                foreach ($this->costs as $cost) {
                    $writeCost($cost, 0);
                }

                // ── Grand total ───────────────────────────────────────
                $text("A{$row}", 'GRAND TOTAL');
                $sheet->mergeCells("A{$row}:B{$row}");
                $this->writeMoney($sheet, $row, [
                    $this->summary['total_budget'] ?? 0,
                    $this->summary['total_release'] ?? 0,
                    $this->summary['total_actual'] ?? 0,
                    $this->summary['total_avail_budget'] ?? 0,
                    $this->summary['total_avail_release'] ?? 0,
                ]);
                $sheet->getStyle("A{$row}:G{$row}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $lastRow = $row;

                // ── Format ────────────────────────────────────────────
                // Nilai minus (over budget / over release) tampil merah, sama
                // seperti di layar.
                $sheet->getStyle("C" . ($headerRow + 1) . ":G{$lastRow}")
                    ->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
                $sheet->getStyle("A{$headerRow}:G{$lastRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');

                $text('A' . ($lastRow + 2), '* Avail. Budget = Budget - Release  |  Avail. Release = Release - Actual');
                $sheet->getStyle('A' . ($lastRow + 2))->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');

                $sheet->getColumnDimension('A')->setWidth(14);
                $sheet->getColumnDimension('B')->setWidth(48);
                foreach (self::MONEY_COLS as $col) {
                    $sheet->getColumnDimension($col)->setWidth(20);
                }
                $sheet->freezePane('A' . ($headerRow + 1));
            },
        ];
    }

    /** Tulis lima kolom angka; null dibiarkan kosong (= "—" di layar). */
    private function writeMoney($sheet, int $row, array $values): void
    {
        foreach (self::MONEY_COLS as $i => $col) {
            if ($values[$i] !== null) {
                $sheet->setCellValueExplicit("{$col}{$row}", (float) $values[$i], DataType::TYPE_NUMERIC);
            }
        }
    }
}
