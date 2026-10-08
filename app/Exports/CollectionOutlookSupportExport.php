<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

// Satu baris per Term Of Payment (Delivery Support). Warna nominal konsisten
// dengan halaman Collection Outlook Support: biru = invoice sudah dikirim
// (menunggu pembayaran), merah = belum dibayar & belum di-invoice / Delay.
class CollectionOutlookSupportExport implements FromArray, WithEvents, ShouldAutoSize
{
    protected Collection $rows;

    /** Baris (1-indexed) → warna font kolom Amount (J). */
    protected array $unpaidRows   = [];
    protected array $invoicedRows = [];

    private const LAST_COL = 'O';

    private const BASIS_LABELS = [
        'percentage' => '% of Revenue',
        'line_item'  => '% of Line Item',
        'fixed'      => 'Fixed amount',
    ];

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function array(): array
    {
        $out = [[
            'Customer', 'Support Name', 'IO Number', 'Type',
            'TOP No.', 'Term Name', 'Line Item', 'Basis', '%', 'Amount',
            'Status', 'Estimated Date', 'Submit Invoice Date', 'Invoice Number', 'Paid Date',
        ]];

        $rowNumber = 1;
        foreach ($this->rows as $r) {
            $rowNumber++;
            $status = $r['status'] ?? '';
            if ($status === 'Invoiced' || ($status === 'Open' && !empty($r['submit_invoice_date']))) {
                $this->invoicedRows[] = $rowNumber;
            } elseif ($status !== 'Paid') {
                $this->unpaidRows[] = $rowNumber;
            }
            $out[] = [
                $r['client_name'],
                $r['support_name'],
                $r['io_number'],
                $r['support_type'],
                $r['term_number'],
                $r['payment_term'],
                $r['line_item_name'] ?? '',
                self::BASIS_LABELS[$r['basis'] ?? 'percentage'] ?? '% of Revenue',
                $r['payment_percentage'],
                $r['amount'],
                $status,
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

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);

                $lastRow = $sheet->getHighestRow();

                // % and Amount as numbers, right-aligned; Amount with thousand separator.
                if ($lastRow >= 2) {
                    $sheet->getStyle("I2:I{$lastRow}")->getNumberFormat()->setFormatCode('0.00');
                    $sheet->getStyle("J2:J{$lastRow}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("I2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                    $sheet->getStyle("E2:E{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                foreach ($this->unpaidRows as $row) {
                    $sheet->getStyle("J{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FFDC2626']],
                    ]);
                }
                foreach ($this->invoicedRows as $row) {
                    $sheet->getStyle("J{$row}")->applyFromArray([
                        'font' => ['bold' => true, 'color' => ['argb' => 'FF1D4ED8']],
                    ]);
                }
            },
        ];
    }
}
