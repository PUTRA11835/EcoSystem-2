<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

// Satu baris per Term Of Payment. Nominal yang BELUM dibayar (status != Paid)
// diberi font merah agar konsisten dengan tampilan halaman Collection Outlook.
class CollectionOutlookExport implements FromArray, WithEvents, ShouldAutoSize
{
    protected Collection $rows;

    /** Baris (1-indexed) yang nominalnya belum dibayar → font merah pada kolom Amount (H). */
    protected array $unpaidRows = [];

    private const LAST_COL = 'M';

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        $out = [[
            'Customer', 'Project Name', 'IO Number', 'Account Executive',
            'TOP No.', 'Term Name', '% of Revenue', 'Amount',
            'Status', 'Estimated Date', 'Submit Invoice Date', 'Invoice Number', 'Paid Date',
        ]];

        $rowNumber = 1;
        foreach ($this->rows as $r) {
            $rowNumber++;
            if (($r['status'] ?? '') !== 'Paid') {
                $this->unpaidRows[] = $rowNumber;
            }
            $out[] = [
                $r['client_name'],
                $r['project_name'],
                $r['io_number'],
                $r['ae_name'],
                $r['term_number'],
                $r['payment_term'],
                $r['payment_percentage'],
                $r['amount'],
                $r['status'],
                $r['estimated_date'],
                $r['submit_invoice_date'],
                $r['invoice_number'],
                $r['paid_date'],
            ];
        }

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet   = $event->sheet->getDelegate();
                $lastCol = self::LAST_COL;

                // Header row
                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $lastRow = $sheet->getHighestRow();

                // % and Amount as numbers, right-aligned; Amount with thousand separator.
                if ($lastRow >= 2) {
                    $sheet->getStyle("G2:G{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
                    $sheet->getStyle("H2:H{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("G2:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                // Font merah untuk nominal yang belum dibayar.
                foreach ($this->unpaidRows as $row) {
                    $sheet->getStyle("H{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFDC2626']],
                    ]);
                }
            },
        ];
    }
}
