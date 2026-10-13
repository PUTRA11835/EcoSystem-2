<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Sheet "Expense Details" — satu baris per actual expense
 * (lihat DeliveryProjectCostExport).
 *
 * Header di baris 1 + AutoFilter supaya bisa langsung difilter per Period /
 * Cost Item di Excel; baris Total memakai SUBTOTAL sehingga ikut filter-nya.
 */
class DeliveryProjectCostExpenseSheet implements WithTitle, WithEvents
{
    private const HEADINGS = [
        'No', 'Cost Type', 'Cost Code', 'Cost Item', 'Period',
        'Expense Name', 'Amount', 'Supporting Document', 'Input Date',
    ];

    public function __construct(private array $expenses)
    {
    }

    public function title(): string
    {
        return 'Expense Details';
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                // String eksplisit: teks user yang diawali "=" tidak jadi formula.
                $text  = fn (string $cell, $value) => $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);

                foreach (self::HEADINGS as $i => $label) {
                    $text(chr(65 + $i) . '1', $label);
                }
                $sheet->getStyle('A1:I1')->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                ]);
                $sheet->freezePane('A2');

                foreach (['A' => 6, 'B' => 16, 'C' => 12, 'D' => 34, 'E' => 12, 'F' => 46, 'G' => 20, 'H' => 40, 'I' => 14] as $col => $width) {
                    $sheet->getColumnDimension($col)->setWidth($width);
                }

                if (!$this->expenses) {
                    $text('A2', 'No expense records.');
                    $sheet->mergeCells('A2:I2');
                    $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                    return;
                }

                $row = 2;
                foreach ($this->expenses as $i => $e) {
                    $sheet->setCellValueExplicit("A{$row}", $i + 1, DataType::TYPE_NUMERIC);
                    $text("B{$row}", $e['cost_type'] === 'indirect' ? 'Indirect Cost' : 'Direct Cost');
                    $text("C{$row}", $e['cost_code']);
                    $text("D{$row}", $e['cost_name']);

                    // Period ditulis sebagai tanggal (tgl 1 bulan tsb.) berformat
                    // "mmm yyyy" supaya urut kronologis saat di-sort di Excel.
                    if ($e['period']) {
                        $sheet->setCellValueExplicit(
                            "E{$row}",
                            Date::PHPToExcel(Carbon::createFromFormat('!Y-m', $e['period'])),
                            DataType::TYPE_NUMERIC
                        );
                    } else {
                        $text("E{$row}", '-');
                    }

                    $text("F{$row}", $e['description']);
                    $sheet->setCellValueExplicit("G{$row}", (float) $e['amount'], DataType::TYPE_NUMERIC);

                    if ($e['document_url']) {
                        $text("H{$row}", $e['document_name'] ?: 'View');
                        $sheet->getCell("H{$row}")->getHyperlink()->setUrl($e['document_url']);
                        $sheet->getStyle("H{$row}")->getFont()->setUnderline(true)->getColor()->setARGB('FF2563EB');
                    } else {
                        $text("H{$row}", '-');
                    }

                    if ($e['created_at']) {
                        $sheet->setCellValueExplicit(
                            "I{$row}",
                            Date::PHPToExcel($e['created_at']->copy()->startOfDay()),
                            DataType::TYPE_NUMERIC
                        );
                    }
                    $row++;
                }
                $lastData = $row - 1;

                $sheet->setAutoFilter("A1:I{$lastData}");

                // Total di luar range AutoFilter; SUBTOTAL(109) hanya menjumlah
                // baris yang lolos filter.
                $text("F{$row}", 'Total');
                $sheet->setCellValue("G{$row}", "=SUBTOTAL(109,G2:G{$lastData})");
                $sheet->getStyle("A{$row}:I{$row}")->applyFromArray([
                    'font'    => ['bold' => true],
                    'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFE5E7EB']],
                    'borders' => ['top' => ['borderStyle' => Border::BORDER_MEDIUM]],
                ]);
                $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

                $sheet->getStyle("G2:G{$row}")->getNumberFormat()->setFormatCode('#,##0');
                $sheet->getStyle("E2:E{$lastData}")->getNumberFormat()->setFormatCode('mmm yyyy');
                $sheet->getStyle("I2:I{$lastData}")->getNumberFormat()->setFormatCode('dd mmm yyyy');
                foreach (['A', 'B', 'C', 'E', 'I'] as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastData}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }
                foreach (['D', 'F', 'H'] as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastData}")->getAlignment()->setWrapText(true);
                }
                $sheet->getStyle("A2:I{$lastData}")->getAlignment()->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getStyle("A1:I{$row}")
                    ->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFD1D5DB');
            },
        ];
    }
}
