<?php

namespace App\Exports;

use App\Models\DeliveryProject;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithPreCalculateFormulas;
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
 * Export Term Of Payment Plan satu Delivery Project (satu sheet):
 *   - info project + scope export (semua termin / hanya yang sudah ber-Invoice No),
 *   - tabel termin dengan kolom yang sama seperti di layar (mode Line Item
 *     menambah kolom Line Item, Basis & Period), AutoFilter + Total SUBTOTAL,
 *   - ringkasan per status, dan per line item untuk mode Line Item.
 *
 * Datanya = ProjectTopPlan::payload() (disiapkan
 * DeliveryProjectPaymentTermController::export()), jadi angka di file selalu
 * sama dengan angka di layar. Teks bebas user ditulis sebagai string eksplisit
 * supaya nilai yang diawali "=" tidak dieksekusi sebagai formula.
 */
class DeliveryProjectPaymentTermExport implements WithTitle, WithEvents, WithPreCalculateFormulas
{
    private const HEADER_FILL = 'FFCC0000';
    private const TOTAL_FILL  = 'FFE5E7EB';
    private const GRID_COLOR  = 'FFD1D5DB';

    /** Warna status mengikuti badge di layar. */
    private const STATUS_COLORS = [
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

    /**
     * @param array  $plan   ProjectTopPlan::payload()
     * @param array  $terms  termin yang diekspor (sudah difilter sesuai scope)
     * @param string $scope  'all' | 'invoiced'
     */
    public function __construct(
        private DeliveryProject $project,
        private array $plan,
        private array $terms,
        private string $scope,
    ) {
    }

    public function title(): string
    {
        return $this->scope === 'invoiced' ? 'Invoiced TOP' : 'Term Of Payment';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $text  = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);

                $lineItemMode = $this->plan['top_mode'] === 'line_item';
                $lineItems    = collect($this->plan['line_items'])->keyBy('id');

                // ── Definisi kolom (key → label, lebar) ───────────────
                $columns = array_filter([
                    'no'        => ['No', 6],
                    'line_item' => $lineItemMode ? ['Line Item', 28] : null,
                    'term'      => ['Payment Term', 30],
                    'period'    => $lineItemMode ? ['Period', 22] : null,
                    'basis'     => $lineItemMode ? ['Basis', 18] : null,
                    'pct'       => ['Payment %', 16],
                    'amount'    => ['Amount', 20],
                    'req'       => ['Payment Requirements / Evidence', 40],
                    'est'       => ['Estimated Date', 15],
                    'submit'    => ['Submit Invoice Date', 15],
                    'invoice'   => ['Invoice No', 22],
                    'paid'      => ['Paid Date', 15],
                    'status'    => ['Status', 12],
                ]);
                $col = [];
                $i = 1;
                foreach (array_keys($columns) as $key) {
                    $col[$key] = Coordinate::stringFromColumnIndex($i++);
                }
                $first = $col['no'];
                $last  = $col['status'];

                // ── Judul + info project ──────────────────────────────
                $text('A1', $this->scope === 'invoiced' ? 'TERM OF PAYMENT PLAN — INVOICED' : 'TERM OF PAYMENT PLAN');
                $sheet->mergeCells("A1:{$last}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $info = [
                    'Project'      => $this->project->name,
                    'IO Number'    => $this->project->io_number ?: '-',
                    'Customer'     => $this->project->client->basicData->name_1 ?? '-',
                    'TOP Type'     => $lineItemMode ? 'Contract Line Item' : '% of Revenue',
                    'Revenue (Sales Data)' => (float) $this->plan['project_revenue'],
                    'Export Scope' => $this->scope === 'invoiced'
                        ? 'Invoiced only — terms that already have an Invoice No'
                        : 'All payment terms',
                    'Exported At'  => now()->format('d M Y H:i'),
                ];
                $row = 2;
                foreach ($info as $label => $value) {
                    $text("A{$row}", $label);
                    $sheet->mergeCells("A{$row}:B{$row}");
                    if (is_float($value)) {
                        $sheet->setCellValueExplicit("C{$row}", $value, DataType::TYPE_NUMERIC);
                        $sheet->getStyle("C{$row}")->getNumberFormat()->setFormatCode('"Rp "#,##0');
                        $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
                    } else {
                        $text("C{$row}", $value);
                    }
                    $sheet->mergeCells("C{$row}:{$last}{$row}");
                    $row++;
                }
                $sheet->getStyle('A2:A' . ($row - 1))->getFont()->setBold(true);

                // ── Header tabel ──────────────────────────────────────
                $headerRow = $row + 1;
                foreach ($columns as $key => [$label, $width]) {
                    $text($col[$key] . $headerRow, $label);
                    $sheet->getColumnDimension($col[$key])->setWidth($width);
                }
                $sheet->getStyle("{$first}{$headerRow}:{$last}{$headerRow}")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::HEADER_FILL]],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                ]);
                $sheet->getRowDimension($headerRow)->setRowHeight(30);
                $sheet->freezePane('A' . ($headerRow + 1));

                $row = $headerRow + 1;

                if (!$this->terms) {
                    $text("A{$row}", $this->scope === 'invoiced'
                        ? 'No payment terms with an Invoice No yet.'
                        : 'No payment terms yet.');
                    $sheet->mergeCells("{$first}{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
                    $sheet->getStyle("{$first}{$headerRow}:{$last}{$row}")
                        ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GRID_COLOR);
                    return;
                }

                // ── Baris termin ──────────────────────────────────────
                foreach ($this->terms as $t) {
                    $sheet->setCellValueExplicit("{$col['no']}{$row}", (int) $t['term_number'], DataType::TYPE_NUMERIC);
                    if ($lineItemMode) {
                        $text("{$col['line_item']}{$row}", $lineItems[$t['contract_line_item_id']]['name'] ?? 'Not linked to a line item');
                        $text("{$col['period']}{$row}", $t['period'] ?: '-');
                        $text("{$col['basis']}{$row}", self::BASIS_LABELS[$t['basis']] ?? '-');
                    }
                    $text("{$col['term']}{$row}", $t['payment_term']);

                    // Fixed tidak punya %, yang ditampilkan porsinya ke line item.
                    $pct = $t['basis'] === 'fixed' ? $t['line_item_share'] : $t['payment_percentage'];
                    if ($pct !== null) {
                        $sheet->setCellValueExplicit("{$col['pct']}{$row}", (float) $pct / 100, DataType::TYPE_NUMERIC);
                        // "0.##%" menampilkan "50.%" di Excel → format dipilih per nilai.
                        $sheet->getStyle("{$col['pct']}{$row}")->getNumberFormat()
                            ->setFormatCode(fmod((float) $pct, 1.0) == 0.0 ? '0%' : '0.00%');
                    } else {
                        $text("{$col['pct']}{$row}", '-');
                    }

                    $sheet->setCellValueExplicit("{$col['amount']}{$row}", (float) $t['amount'], DataType::TYPE_NUMERIC);
                    $text("{$col['req']}{$row}", $t['requirements'] ?: '-');
                    $this->writeDate($sheet, "{$col['est']}{$row}", $t['estimated_date']);
                    $this->writeDate($sheet, "{$col['submit']}{$row}", $t['submit_invoice_date']);
                    $text("{$col['invoice']}{$row}", trim((string) $t['invoice_number']) ?: '-');
                    $this->writeDate($sheet, "{$col['paid']}{$row}", $t['paid_date']);

                    $text("{$col['status']}{$row}", $t['status']);
                    if ($colors = self::STATUS_COLORS[$t['status']] ?? null) {
                        $sheet->getStyle("{$col['status']}{$row}")->applyFromArray([
                            'font' => ['bold' => true, 'color' => ['argb' => $colors[1]]],
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $colors[0]]],
                        ]);
                    }
                    $row++;
                }
                $firstData = $headerRow + 1;
                $lastData  = $row - 1;

                $sheet->setAutoFilter("{$first}{$headerRow}:{$last}{$lastData}");

                // ── Total (di luar AutoFilter; SUBTOTAL ikut filter) ───
                $amountCol = $col['amount'];
                $labelEnd  = Coordinate::stringFromColumnIndex(Coordinate::columnIndexFromString($col['pct']));
                $text("A{$row}", 'TOTAL');
                $sheet->mergeCells("A{$row}:{$labelEnd}{$row}");
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->setCellValue("{$amountCol}{$row}", "=SUBTOTAL(109,{$amountCol}{$firstData}:{$amountCol}{$lastData})");
                $sheet->getStyle("{$first}{$row}:{$last}{$row}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TOTAL_FILL]],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $totalRow = $row;

                // ── Format tabel ──────────────────────────────────────
                $sheet->getStyle("{$amountCol}{$firstData}:{$amountCol}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0');
                foreach (['est', 'submit', 'paid'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getNumberFormat()->setFormatCode('dd mmm yyyy');
                }
                foreach (['no', 'pct', 'est', 'submit', 'paid', 'status'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                if ($lineItemMode) {
                    $sheet->getStyle("{$col['basis']}{$firstData}:{$col['basis']}{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                foreach (['line_item', 'term', 'period', 'req', 'invoice'] as $key) {
                    if (isset($col[$key])) {
                        $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getAlignment()->setWrapText(true);
                    }
                }
                $sheet->getStyle("{$first}{$firstData}:{$last}{$lastData}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("{$first}{$headerRow}:{$last}{$totalRow}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GRID_COLOR);

                // ── Ringkasan ─────────────────────────────────────────
                $row = $totalRow + 3;
                $row = $this->writeStatusSummary($sheet, $text, $row);
                if ($lineItemMode && $lineItems->isNotEmpty()) {
                    $this->writeLineItemSummary($sheet, $text, $row + 2, $lineItems);
                }

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setFitToWidth(1)->setFitToHeight(0);
            },
        ];
    }

    /** Tabel "Summary by Status" (B..D). Mengembalikan baris terakhir yang ditulis. */
    private function writeStatusSummary($sheet, callable $text, int $row): int
    {
        $text("B{$row}", 'SUMMARY BY STATUS');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $head = $row;
        foreach (['B' => 'Status', 'C' => 'Terms', 'D' => 'Amount'] as $c => $label) {
            $text("{$c}{$row}", $label);
        }
        $this->styleSubHeader($sheet, "B{$row}:D{$row}");
        $row++;

        $terms = collect($this->terms);
        foreach (array_keys(self::STATUS_COLORS) as $status) {
            $own = $terms->where('status', $status);
            if ($own->isEmpty()) {
                continue;
            }
            $text("B{$row}", $status);
            [$bg, $fg] = self::STATUS_COLORS[$status];
            $sheet->getStyle("B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['argb' => $fg]],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
            ]);
            $sheet->setCellValueExplicit("C{$row}", $own->count(), DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("D{$row}", (float) $own->sum('amount'), DataType::TYPE_NUMERIC);
            $row++;
        }

        $text("B{$row}", 'Total');
        $sheet->setCellValue("C{$row}", '=SUM(C' . ($head + 1) . ":C" . ($row - 1) . ')');
        $sheet->setCellValue("D{$row}", '=SUM(D' . ($head + 1) . ":D" . ($row - 1) . ')');
        $this->styleTotal($sheet, "B{$row}:D{$row}");

        $sheet->getStyle("C" . ($head + 1) . ":C{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D" . ($head + 1) . ":D{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->grid($sheet, "B{$head}:D{$row}");

        return $row;
    }

    /**
     * Tabel "Summary by Line Item" (B..F): nilai kontrak vs total termin.
     * "Billed in TOP" selalu dari seluruh termin (sama seperti layar),
     * sedangkan "In This Export" mengikuti scope export.
     */
    private function writeLineItemSummary($sheet, callable $text, int $row, $lineItems): void
    {
        $text("B{$row}", 'SUMMARY BY LINE ITEM');
        $sheet->getStyle("B{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $head = $row;
        $labels = ['B' => 'Line Item', 'C' => 'Type', 'D' => 'Contract Value', 'E' => 'Billed in TOP', 'F' => 'In This Export'];
        foreach ($labels as $c => $label) {
            $text("{$c}{$row}", $label);
        }
        $this->styleSubHeader($sheet, "B{$row}:F{$row}");
        $row++;

        $exported = collect($this->terms)->groupBy('contract_line_item_id');
        foreach ($lineItems as $li) {
            $text("B{$row}", $li['name']);
            $text("C{$row}", $li['type_label']);
            $sheet->setCellValueExplicit("D{$row}", (float) $li['total'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("E{$row}", (float) $li['billed_total'], DataType::TYPE_NUMERIC);
            $sheet->setCellValueExplicit("F{$row}", (float) ($exported[$li['id']] ?? collect())->sum('amount'), DataType::TYPE_NUMERIC);
            $row++;
        }

        $text("B{$row}", 'Total');
        foreach (['D', 'E', 'F'] as $c) {
            $sheet->setCellValue("{$c}{$row}", "=SUM({$c}" . ($head + 1) . ":{$c}" . ($row - 1) . ')');
        }
        $this->styleTotal($sheet, "B{$row}:F{$row}");

        $sheet->getStyle("B" . ($head + 1) . ":C{$row}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("D" . ($head + 1) . ":F{$row}")->getNumberFormat()->setFormatCode('#,##0');
        $this->grid($sheet, "B{$head}:F{$row}");
    }

    private function writeDate($sheet, string $cell, ?string $ymd): void
    {
        if ($ymd) {
            $sheet->setCellValueExplicit($cell, Date::PHPToExcel(Carbon::createFromFormat('!Y-m-d', $ymd)), DataType::TYPE_NUMERIC);
        } else {
            $sheet->setCellValueExplicit($cell, '-', DataType::TYPE_STRING);
        }
    }

    private function styleSubHeader($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF374151']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
        ]);
    }

    private function styleTotal($sheet, string $range): void
    {
        $sheet->getStyle($range)->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::TOTAL_FILL]],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);
    }

    private function grid($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB(self::GRID_COLOR);
    }
}
