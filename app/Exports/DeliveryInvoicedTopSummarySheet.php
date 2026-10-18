<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Summary by Project" (lihat DeliveryInvoicedTopExport):
 *   - per project: revenue, jumlah termin ber-invoice, total invoiced,
 *     sudah dibayar (status Paid) dan belum dibayar;
 *   - ringkasan per status termin.
 */
class DeliveryInvoicedTopSummarySheet implements WithTitle, WithEvents
{
    private const HEADER_ROW = 3;

    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'Summary by Project';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $text  = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
                $num   = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC);

                $text('A1', 'INVOICED TERM OF PAYMENT — SUMMARY BY PROJECT');
                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                foreach (['A' => 6, 'B' => 15, 'C' => 30, 'D' => 38, 'E' => 19, 'F' => 12, 'G' => 19, 'H' => 19, 'I' => 19] as $c => $w) {
                    $sheet->getColumnDimension($c)->setWidth($w);
                }

                // ── Per project ───────────────────────────────────────
                $h = self::HEADER_ROW;
                $labels = ['No', 'IO Number', 'Customer', 'Project Name', 'Revenue (Sales Data)', 'Invoiced Terms', 'Total Invoiced', 'Paid', 'Unpaid'];
                foreach ($labels as $i => $label) {
                    $text(chr(65 + $i) . $h, $label);
                }
                $this->styleHeader($sheet, "A{$h}:I{$h}", 'FFCC0000');
                $sheet->getRowDimension($h)->setRowHeight(30);
                $sheet->freezePane('A' . ($h + 1));

                $row = $h + 1;
                $projects = collect($this->rows)->groupBy('project_id');
                if ($projects->isEmpty()) {
                    $text("A{$row}", 'No payment terms with an Invoice No yet.');
                    $sheet->mergeCells("A{$row}:I{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
                    $this->grid($sheet, "A{$h}:I{$row}");
                    return;
                }

                $n = 0;
                foreach ($projects as $terms) {
                    $p     = $terms->first();
                    $total = $terms->sum('amount');
                    $paid  = $terms->where('status', 'Paid')->sum('amount');

                    $sheet->setCellValueExplicit("A{$row}", ++$n, DataType::TYPE_NUMERIC);
                    $text("B{$row}", $p['io_number'] ?: '-');
                    $text("C{$row}", $p['customer'] ?: '-');
                    $text("D{$row}", $p['project_name']);
                    $num("E{$row}", $p['revenue']);
                    $sheet->setCellValueExplicit("F{$row}", $terms->count(), DataType::TYPE_NUMERIC);
                    $num("G{$row}", $total);
                    $num("H{$row}", $paid);
                    $sheet->setCellValue("I{$row}", "=G{$row}-H{$row}");
                    $row++;
                }
                $firstData = $h + 1;
                $lastData  = $row - 1;
                $sheet->setAutoFilter("A{$h}:I{$lastData}");

                $text("A{$row}", 'TOTAL');
                $sheet->mergeCells("A{$row}:D{$row}");
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                foreach (['E', 'F', 'G', 'H', 'I'] as $c) {
                    $sheet->setCellValue("{$c}{$row}", "=SUBTOTAL(109,{$c}{$firstData}:{$c}{$lastData})");
                }
                $this->styleTotal($sheet, "A{$row}:I{$row}");
                $totalRow = $row;

                $sheet->getStyle("E{$firstData}:I{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("F{$firstData}:F{$totalRow}")->getNumberFormat()->setFormatCode('0');
                $sheet->getStyle("A{$firstData}:B{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("F{$firstData}:F{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("C{$firstData}:D{$lastData}")->getAlignment()->setWrapText(true);
                $sheet->getStyle("A{$firstData}:I{$lastData}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $this->grid($sheet, "A{$h}:I{$totalRow}");

                $note = $totalRow + 1;
                $text("A{$note}", '* Paid = terms with status Paid  |  Unpaid = Total Invoiced - Paid');
                $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');

                // ── Per status ────────────────────────────────────────
                $row = $note + 2;
                $text("B{$row}", 'SUMMARY BY STATUS');
                $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
                $row++;
                $sh = $row;
                foreach (['B' => 'Status', 'C' => 'Terms', 'D' => 'Amount'] as $c => $label) {
                    $text("{$c}{$row}", $label);
                }
                $this->styleHeader($sheet, "B{$row}:D{$row}", 'FF374151');
                $row++;

                $all = collect($this->rows);
                foreach (DeliveryInvoicedTopDetailSheet::STATUS_COLORS as $status => [$bg, $fg]) {
                    $own = $all->where('status', $status);
                    if ($own->isEmpty()) {
                        continue;
                    }
                    $text("B{$row}", $status);
                    $sheet->getStyle("B{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => $fg]],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
                    ]);
                    $sheet->setCellValueExplicit("C{$row}", $own->count(), DataType::TYPE_NUMERIC);
                    $num("D{$row}", $own->sum('amount'));
                    $row++;
                }
                $text("B{$row}", 'Total');
                $sheet->setCellValue("C{$row}", '=SUM(C' . ($sh + 1) . ':C' . ($row - 1) . ')');
                $sheet->setCellValue("D{$row}", '=SUM(D' . ($sh + 1) . ':D' . ($row - 1) . ')');
                $this->styleTotal($sheet, "B{$row}:D{$row}");
                $sheet->getStyle('C' . ($sh + 1) . ":C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle('D' . ($sh + 1) . ":D{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $this->grid($sheet, "B{$sh}:D{$row}");
            },
        ];
    }

    private function styleHeader($sheet, string $range, string $fill): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $fill]],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
    }

    private function styleTotal($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
    }

    private function grid($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
    }
}
