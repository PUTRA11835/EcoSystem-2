<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Revenue Summary" (lihat DeliveryProjectRevenueExport): rekap revenue,
 * cost, gross profit, dan posisi penagihan per Category, per Project Type,
 * dan per Customer. Setiap blok ditutup baris Total.
 */
class DeliveryProjectRevenueSummarySheet implements WithTitle, WithEvents
{
    /** Kolom B..M setelah kolom grup (A). */
    private const HEADERS = [
        'Projects', 'Revenue', 'Plan Cost', 'Gross Profit', '% Gross Profit',
        'Actual Cost', 'Actual Gross Profit', '% Actual Gross Profit',
        'Invoiced', 'Paid', 'Unbilled', 'Receivable',
    ];

    private const CATEGORY_ORDER = ['Open', 'In Process', 'Closed'];

    public function __construct(private array $rows)
    {
    }

    public function title(): string
    {
        return 'Revenue Summary';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                $sheet->setCellValueExplicit('A1', 'DELIVERY PROJECT REVENUE — SUMMARY', DataType::TYPE_STRING);
                $sheet->mergeCells('A1:M1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                $sheet->setCellValueExplicit('A2', 'Exported At: ' . now()->format('d M Y H:i'), DataType::TYPE_STRING);
                $sheet->getStyle('A2')->getFont()->getColor()->setARGB('FF6B7280');

                $sheet->getColumnDimension('A')->setWidth(36);
                $sheet->getColumnDimension('B')->setWidth(10);
                foreach (range('C', 'M') as $c) {
                    $sheet->getColumnDimension($c)->setWidth(19);
                }
                $sheet->getColumnDimension('F')->setWidth(12);
                $sheet->getColumnDimension('I')->setWidth(12);

                $all = collect($this->rows);
                $row = 4;

                $byCategory = $all->groupBy(fn ($r) => $r['category'] ?: '-')
                    ->sortBy(fn ($g, $k) => ($i = array_search($k, self::CATEGORY_ORDER, true)) === false ? 99 : $i);
                $row = $this->block($sheet, $row, 'BY CATEGORY', 'Category', $byCategory);

                $byType = $all->groupBy(fn ($r) => $r['project_type'] ?: '-')->sortKeys(SORT_NATURAL | SORT_FLAG_CASE);
                $row = $this->block($sheet, $row + 2, 'BY PROJECT TYPE', 'Project Type', $byType);

                // Customer diurutkan dari revenue terbesar.
                $byCustomer = $all->groupBy(fn ($r) => $r['customer'] ?: '-')
                    ->sortByDesc(fn ($g) => $g->sum('revenue'));
                $row = $this->block($sheet, $row + 2, 'BY CUSTOMER', 'Customer', $byCustomer);

                $note = $row + 2;
                $sheet->setCellValueExplicit("A{$note}", '* Unbilled = Revenue - Invoiced  |  Receivable = Invoiced - Paid  |  Detail per project on sheet "Project Revenue"', DataType::TYPE_STRING);
                $sheet->getStyle("A{$note}")->getFont()->setItalic(true)->getColor()->setARGB('FF6B7280');
            },
        ];
    }

    /**
     * Tulis satu blok rekap; kembalikan nomor baris Total-nya.
     *
     * @param  Collection<string, Collection<int, array>>  $groups
     */
    private function block($sheet, int $row, string $title, string $groupLabel, Collection $groups): int
    {
        $sheet->setCellValueExplicit("A{$row}", $title, DataType::TYPE_STRING);
        $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(12);
        $row++;

        $h = $row;
        $sheet->setCellValueExplicit("A{$h}", $groupLabel, DataType::TYPE_STRING);
        foreach (self::HEADERS as $i => $label) {
            $sheet->setCellValueExplicit(chr(66 + $i) . $h, $label, DataType::TYPE_STRING);
        }
        $sheet->getStyle("A{$h}:M{$h}")->applyFromArray([
            'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension($h)->setRowHeight(30);
        $row++;

        $first = $row;
        foreach ($groups as $label => $items) {
            $this->line($sheet, $row, (string) $label, $items->count(), [
                'revenue'     => $items->sum('revenue'),
                'plan_cost'   => $items->sum('plan_cost'),
                'actual_cost' => $items->sum('actual_cost'),
                'invoiced'    => $items->sum('invoiced'),
                'paid'        => $items->sum('paid'),
            ]);
            $row++;
        }
        $lastData = $row - 1;

        // Total: kolom nilai = SUM, kolom persentase dihitung ulang dari total.
        $sheet->setCellValueExplicit("A{$row}", 'Total', DataType::TYPE_STRING);
        foreach (['B', 'C', 'D', 'E', 'G', 'H', 'J', 'K', 'L', 'M'] as $c) {
            $sheet->setCellValue("{$c}{$row}", $lastData >= $first ? "=SUM({$c}{$first}:{$c}{$lastData})" : 0);
        }
        $sheet->setCellValue("F{$row}", "=IF(C{$row}=0,0,E{$row}/C{$row})");
        $sheet->setCellValue("I{$row}", "=IF(C{$row}=0,0,H{$row}/C{$row})");
        $sheet->getStyle("A{$row}:M{$row}")->applyFromArray([
            'font'    => ['bold' => true],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
            'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
        ]);

        $sheet->getStyle("C{$first}:M{$row}")->getNumberFormat()->setFormatCode('#,##0;[Red]-#,##0');
        $sheet->getStyle("F{$first}:F{$row}")->getNumberFormat()->setFormatCode('0.00%;[Red]-0.00%');
        $sheet->getStyle("I{$first}:I{$row}")->getNumberFormat()->setFormatCode('0.00%;[Red]-0.00%');
        $sheet->getStyle("B{$first}:B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A{$first}:A{$lastData}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("A{$h}:M{$row}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');

        return $row;
    }

    private function line($sheet, int $row, string $label, int $count, array $v): void
    {
        $num = fn (string $c, $value) => $sheet->setCellValueExplicit("{$c}{$row}", (float) $value, DataType::TYPE_NUMERIC);

        $sheet->setCellValueExplicit("A{$row}", $label, DataType::TYPE_STRING);
        $sheet->setCellValueExplicit("B{$row}", $count, DataType::TYPE_NUMERIC);
        $num('C', $v['revenue']);
        $num('D', $v['plan_cost']);
        $sheet->setCellValue("E{$row}", "=C{$row}-D{$row}");
        $sheet->setCellValue("F{$row}", "=IF(C{$row}=0,0,E{$row}/C{$row})");
        $num('G', $v['actual_cost']);
        $sheet->setCellValue("H{$row}", "=C{$row}-G{$row}");
        $sheet->setCellValue("I{$row}", "=IF(C{$row}=0,0,H{$row}/C{$row})");
        $num('J', $v['invoiced']);
        $num('K', $v['paid']);
        $sheet->setCellValue("L{$row}", "=C{$row}-J{$row}");
        $sheet->setCellValue("M{$row}", "=J{$row}-K{$row}");
    }
}
