<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;

/**
 * Sheet "Invoiced TOP" — satu baris per termin ber-Invoice No
 * (lihat DeliveryInvoicedTopExport). AutoFilter di header + Total SUBTOTAL
 * supaya bisa langsung difilter per project / status / customer di Excel.
 */
class DeliveryInvoicedTopDetailSheet implements WithTitle, WithEvents
{
    /** Warna status mengikuti badge di layar. */
    public const STATUS_COLORS = [
        'Open'     => ['FFFEF3C7', 'FF92400E'],
        'Invoiced' => ['FFDBEAFE', 'FF1E40AF'],
        'Paid'     => ['FFDCFCE7', 'FF166534'],
        'Delay'    => ['FFFEE2E2', 'FFB91C1C'],
    ];

    private const BASIS_LABELS = [
        'percentage' => '% of Revenue',
        'line_item'  => '% of Line Item',
        'fixed'      => 'Fixed Amount',
    ];

    /** key => [label, lebar] — urutan = urutan kolom. */
    private const COLUMNS = [
        'no'       => ['No', 6],
        'io'       => ['IO Number', 15],
        'customer' => ['Customer', 30],
        'project'  => ['Project Name', 38],
        'category' => ['Category', 12],
        'term_no'  => ['Term No', 9],
        'line'     => ['Line Item', 24],
        'term'     => ['Payment Term', 28],
        'period'   => ['Period', 16],
        'basis'    => ['Basis', 16],
        'pct'      => ['Payment %', 12],
        'amount'   => ['Amount', 19],
        'req'      => ['Payment Requirements / Evidence', 36],
        'est'      => ['Estimated Date', 14],
        'submit'   => ['Submit Invoice Date', 14],
        'invoice'  => ['Invoice No', 22],
        'paid'     => ['Paid Date', 14],
        'status'   => ['Status', 12],
    ];

    private const HEADER_ROW = 6;

    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'Invoiced TOP';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $text  = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);

                $col = [];
                $i = 1;
                foreach (array_keys(self::COLUMNS) as $key) {
                    $col[$key] = Coordinate::stringFromColumnIndex($i++);
                }
                $last = $col['status'];

                // ── Judul + info ──────────────────────────────────────
                $text('A1', 'INVOICED TERM OF PAYMENT — ALL DELIVERY PROJECTS');
                $sheet->mergeCells("A1:{$last}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $info = [
                    'Projects'       => collect($this->rows)->pluck('project_id')->unique()->count() . ' project(s)',
                    'Invoiced Terms' => count($this->rows) . ' term(s) with an Invoice No',
                    'Exported At'    => now()->format('d M Y H:i'),
                ];
                $row = 2;
                foreach ($info as $label => $value) {
                    $text("A{$row}", $label);
                    $sheet->mergeCells("A{$row}:B{$row}");
                    $text("C{$row}", $value);
                    $sheet->mergeCells("C{$row}:F{$row}");
                    $row++;
                }
                $sheet->getStyle('A2:A4')->getFont()->setBold(true);

                // ── Header ────────────────────────────────────────────
                $h = self::HEADER_ROW;
                foreach (self::COLUMNS as $key => [$label, $width]) {
                    $text($col[$key] . $h, $label);
                    $sheet->getColumnDimension($col[$key])->setWidth($width);
                }
                $sheet->getStyle("A{$h}:{$last}{$h}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $sheet->getRowDimension($h)->setRowHeight(30);
                $sheet->freezePane('A' . ($h + 1));

                $row = $h + 1;
                if (!$this->rows) {
                    $text("A{$row}", 'No payment terms with an Invoice No yet.');
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
                    $this->grid($sheet, "A{$h}:{$last}{$row}");
                    return;
                }

                // ── Baris termin ──────────────────────────────────────
                foreach ($this->rows as $n => $r) {
                    $sheet->setCellValueExplicit("{$col['no']}{$row}", $n + 1, DataType::TYPE_NUMERIC);
                    $text("{$col['io']}{$row}", $r['io_number'] ?: '-');
                    $text("{$col['customer']}{$row}", $r['customer'] ?: '-');
                    $text("{$col['project']}{$row}", $r['project_name']);
                    $text("{$col['category']}{$row}", $r['category'] ?: '-');
                    $sheet->setCellValueExplicit("{$col['term_no']}{$row}", (int) $r['term_number'], DataType::TYPE_NUMERIC);
                    $text("{$col['line']}{$row}", $r['line_item'] ?: '-');
                    $text("{$col['term']}{$row}", $r['payment_term']);
                    $text("{$col['period']}{$row}", $r['period'] ?: '-');
                    $text("{$col['basis']}{$row}", self::BASIS_LABELS[$r['basis']] ?? '-');

                    if ($r['pct'] !== null) {
                        $sheet->setCellValueExplicit("{$col['pct']}{$row}", (float) $r['pct'] / 100, DataType::TYPE_NUMERIC);
                        // "0.##%" menampilkan "50.%" di Excel → format dipilih per nilai.
                        $sheet->getStyle("{$col['pct']}{$row}")->getNumberFormat()
                            ->setFormatCode(fmod((float) $r['pct'], 1.0) == 0.0 ? '0%' : '0.00%');
                    } else {
                        $text("{$col['pct']}{$row}", '-');
                    }

                    $sheet->setCellValueExplicit("{$col['amount']}{$row}", (float) $r['amount'], DataType::TYPE_NUMERIC);
                    $text("{$col['req']}{$row}", $r['requirements'] ?: '-');
                    $this->writeDate($sheet, "{$col['est']}{$row}", $r['estimated_date']);
                    $this->writeDate($sheet, "{$col['submit']}{$row}", $r['submit_invoice_date']);
                    $text("{$col['invoice']}{$row}", $r['invoice_number']);
                    $this->writeDate($sheet, "{$col['paid']}{$row}", $r['paid_date']);

                    $text("{$col['status']}{$row}", $r['status']);
                    if ($colors = self::STATUS_COLORS[$r['status']] ?? null) {
                        $sheet->getStyle("{$col['status']}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => $colors[1]]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors[0]]],
                        ]);
                    }
                    $row++;
                }
                $firstData = $h + 1;
                $lastData  = $row - 1;

                $sheet->setAutoFilter("A{$h}:{$last}{$lastData}");

                // ── Total (di luar AutoFilter; SUBTOTAL ikut filter) ───
                $a = $col['amount'];
                $text("A{$row}", 'TOTAL');
                $sheet->mergeCells("A{$row}:{$col['pct']}{$row}");
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->setCellValue("{$a}{$row}", "=SUBTOTAL(109,{$a}{$firstData}:{$a}{$lastData})");
                $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $totalRow = $row;

                // ── Format ────────────────────────────────────────────
                $sheet->getStyle("{$a}{$firstData}:{$a}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                foreach (['est', 'submit', 'paid'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getNumberFormat()->setFormatCode('dd mmm yyyy');
                }
                foreach (['no', 'io', 'category', 'term_no', 'basis', 'pct', 'est', 'submit', 'paid', 'status'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                foreach (['customer', 'project', 'line', 'term', 'period', 'req', 'invoice'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getAlignment()->setWrapText(true);
                }
                $sheet->getStyle("A{$firstData}:{$last}{$lastData}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $this->grid($sheet, "A{$h}:{$last}{$totalRow}");

                $sheet->getPageSetup()->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }

    private function writeDate($sheet, string $cell, ?string $ymd): void
    {
        if ($ymd) {
            $sheet->setCellValueExplicit($cell, Date::PHPToExcel(Carbon::createFromFormat('!Y-m-d', $ymd)), DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValueExplicit($cell, '-', DataType::TYPE_STRING);
        }
    }

    private function grid($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
    }
}
