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
 * Sheet "Project Revenue" — satu baris per Delivery Project
 * (lihat DeliveryProjectRevenueExport). Gross profit & persentase ditulis
 * sebagai formula supaya tetap benar bila angka diedit di Excel; AutoFilter
 * + Total SUBTOTAL supaya total ikut menyempit saat difilter.
 */
class DeliveryProjectRevenueDetailSheet implements WithTitle, WithEvents
{
    /** key => [label, lebar] — urutan = urutan kolom. */
    private const COLUMNS = [
        'no'          => ['No', 6],
        'io'          => ['IO Number', 15],
        'customer'    => ['Customer', 30],
        'type'        => ['Project Type', 16],
        'project'     => ['Project Name', 38],
        'ae'          => ['AE', 24],
        'owner'       => ['Project Owner', 24],
        'category'    => ['Category', 12],
        'status'      => ['Project Status', 13],
        'start'       => ['Contract Start', 14],
        'end'         => ['Contract End', 14],
        'revenue'     => ['Revenue', 19],
        'plan_cost'   => ['Plan Cost', 19],
        'gp'          => ['Gross Profit', 19],
        'gp_pct'      => ['% Gross Profit', 11],
        'actual_cost' => ['Actual Cost', 19],
        'agp'         => ['Actual Gross Profit', 19],
        'agp_pct'     => ['% Actual Gross Profit', 11],
        'terms'       => ['TOP Terms', 9],
        'top'         => ['TOP Planned Amount', 19],
        'invoiced'    => ['Invoiced', 19],
        'paid'        => ['Paid', 19],
        'unbilled'    => ['Unbilled (Revenue - Invoiced)', 19],
        'receivable'  => ['Receivable (Invoiced - Paid)', 19],
    ];

    /** Kolom angka rupiah — dijumlah di baris TOTAL. */
    private const MONEY = ['revenue', 'plan_cost', 'gp', 'actual_cost', 'agp', 'top', 'invoiced', 'paid', 'unbilled', 'receivable'];

    /** Warna badge di layar. */
    private const CATEGORY_COLORS = [
        'Open'       => ['FFFEF9C3', 'FF854D0E'],
        'In Process' => ['FFDBEAFE', 'FF1E40AF'],
        'Closed'     => ['FFF3F4F6', 'FF374151'],
    ];
    private const STATUS_COLORS = [
        'On Track'    => ['FFDCFCE7', 'FF166534'],
        'At Risk'     => ['FFFEF3C7', 'FF92400E'],
        'At Critical' => ['FFFEE2E2', 'FFB91C1C'],
    ];

    private const HEADER_ROW = 6;

    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'Project Revenue';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $text  = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
                $num   = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (float) $value, DataType::TYPE_NUMERIC);

                $col = [];
                $i = 1;
                foreach (array_keys(self::COLUMNS) as $key) {
                    $col[$key] = Coordinate::stringFromColumnIndex($i++);
                }
                $last  = $col['receivable'];

                // ── Judul + info ──────────────────────────────────────
                $text('A1', 'DELIVERY PROJECT REVENUE — ALL PROJECTS');
                $sheet->mergeCells("A1:{$last}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $info = [
                    'Projects'    => count($this->rows) . ' project(s)',
                    'Basis'       => 'Revenue, Plan Cost & Actual Cost from Delivery Information; TOP amounts recalculated from current revenue',
                    'Exported At' => now()->format('d M Y H:i'),
                ];
                $row = 2;
                foreach ($info as $label => $value) {
                    $text("A{$row}", $label);
                    $sheet->mergeCells("A{$row}:B{$row}");
                    $text("C{$row}", $value);
                    $sheet->mergeCells("C{$row}:H{$row}");
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
                $sheet->getRowDimension($h)->setRowHeight(32);
                $sheet->freezePane($col['project'] . ($h + 1));

                $row = $h + 1;
                if (!$this->rows) {
                    $text("A{$row}", 'No delivery projects yet.');
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    $sheet->getStyle("A{$row}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
                    $this->grid($sheet, "A{$h}:{$last}{$row}");
                    return;
                }

                // ── Baris project ─────────────────────────────────────
                foreach ($this->rows as $n => $r) {
                    $c = fn (string $key) => $col[$key] . $row;

                    $sheet->setCellValueExplicit($c('no'), $n + 1, DataType::TYPE_NUMERIC);
                    $text($c('io'), $r['io_number'] ?: '-');
                    $text($c('customer'), $r['customer'] ?: '-');
                    $text($c('type'), $r['project_type'] ?: '-');
                    $text($c('project'), $r['project_name']);
                    $text($c('ae'), $r['ae_name'] ?: '-');
                    $text($c('owner'), $r['project_owner'] ?: '-');
                    $text($c('category'), $r['category'] ?: '-');
                    $text($c('status'), $r['status'] ?: '-');
                    $this->date($sheet, $c('start'), $r['contract_start_date']);
                    $this->date($sheet, $c('end'), $r['contract_end_date']);

                    $num($c('revenue'), $r['revenue']);
                    $num($c('plan_cost'), $r['plan_cost']);
                    $sheet->setCellValue($c('gp'), "={$c('revenue')}-{$c('plan_cost')}");
                    $sheet->setCellValue($c('gp_pct'), "=IF({$c('revenue')}=0,0,{$c('gp')}/{$c('revenue')})");
                    $num($c('actual_cost'), $r['actual_cost']);
                    $sheet->setCellValue($c('agp'), "={$c('revenue')}-{$c('actual_cost')}");
                    $sheet->setCellValue($c('agp_pct'), "=IF({$c('revenue')}=0,0,{$c('agp')}/{$c('revenue')})");

                    $sheet->setCellValueExplicit($c('terms'), (int) $r['top_terms'], DataType::TYPE_NUMERIC);
                    $num($c('top'), $r['top_amount']);
                    $num($c('invoiced'), $r['invoiced']);
                    $num($c('paid'), $r['paid']);
                    $sheet->setCellValue($c('unbilled'), "={$c('revenue')}-{$c('invoiced')}");
                    $sheet->setCellValue($c('receivable'), "={$c('invoiced')}-{$c('paid')}");

                    $this->badge($sheet, $c('category'), self::CATEGORY_COLORS[$r['category']] ?? null);
                    $this->badge($sheet, $c('status'), self::STATUS_COLORS[$r['status']] ?? null);

                    // Revenue belum diisi → tandai supaya mudah dilengkapi.
                    if ((float) $r['revenue'] <= 0) {
                        $sheet->getStyle($c('revenue'))->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEF2F2');
                    }
                    $row++;
                }
                $firstData = $h + 1;
                $lastData  = $row - 1;
                $sheet->setAutoFilter("A{$h}:{$last}{$lastData}");

                // ── Total ─────────────────────────────────────────────
                $text("A{$row}", 'TOTAL');
                $sheet->mergeCells("A{$row}:{$col['end']}{$row}");
                $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                foreach ([...self::MONEY, 'terms'] as $key) {
                    $k = $col[$key];
                    $sheet->setCellValue("{$k}{$row}", "=SUBTOTAL(109,{$k}{$firstData}:{$k}{$lastData})");
                }
                $sheet->setCellValue("{$col['gp_pct']}{$row}", "=IF({$col['revenue']}{$row}=0,0,{$col['gp']}{$row}/{$col['revenue']}{$row})");
                $sheet->setCellValue("{$col['agp_pct']}{$row}", "=IF({$col['revenue']}{$row}=0,0,{$col['agp']}{$row}/{$col['revenue']}{$row})");
                $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $totalRow = $row;

                // ── Format ────────────────────────────────────────────
                foreach (self::MONEY as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$totalRow}")->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
                }
                foreach (['gp_pct', 'agp_pct'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$totalRow}")->getNumberFormat()->setFormatCode('0.00%;[Red]-0.00%');
                }
                $sheet->getStyle("{$col['start']}{$firstData}:{$col['end']}{$lastData}")->getNumberFormat()->setFormatCode('dd mmm yyyy');

                foreach (['no', 'io', 'category', 'status', 'start', 'end', 'terms'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                foreach (['customer', 'project', 'ae', 'owner'] as $key) {
                    $sheet->getStyle("{$col[$key]}{$firstData}:{$col[$key]}{$lastData}")->getAlignment()->setWrapText(true);
                }
                $sheet->getStyle("A{$firstData}:{$last}{$lastData}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $this->grid($sheet, "A{$h}:{$last}{$totalRow}");

                $note = $totalRow + 1;
                $text("A{$note}", '* Gross Profit = Revenue - Plan Cost  |  Actual Gross Profit = Revenue - Actual Cost  |  Invoiced = TOP terms with an Invoice No  |  Paid = TOP terms with status Paid  |  Highlighted revenue = not filled yet');
                $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');

                $sheet->getPageSetup()
                    ->setOrientation(PageSetup::ORIENTATION_LANDSCAPE)
                    ->setPaperSize(PageSetup::PAPERSIZE_A4)
                    ->setFitToWidth(1)
                    ->setFitToHeight(0);
                $sheet->getPageSetup()->setRowsToRepeatAtTopByStartAndEnd($h, $h);
            },
        ];
    }

    private function date($sheet, string $cell, $value): void
    {
        if (!$value) {
            $sheet->setCellValueExplicit($cell, '-', DataType::TYPE_STRING);
            return;
        }
        $sheet->setCellValue($cell, Date::PHPToExcel(Carbon::parse($value)->startOfDay()));
    }

    private function badge($sheet, string $cell, ?array $colors): void
    {
        if (!$colors) {
            return;
        }
        [$bg, $fg] = $colors;
        $sheet->getStyle($cell)->applyFromArray([
            'font' => ['bold' => true, 'color' => ['argb' => $fg]],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $bg]],
        ]);
    }

    private function grid($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
    }
}
