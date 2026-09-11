<?php

namespace App\Exports;

use App\Services\CashAdvance\CashAdvanceReportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Ekspor dokumen Cash Advance Report — bentuk DOKUMEN, bukan tabel rekap.
 *
 * Kembar dengan CashAdvanceDocumentExport (D114: satu kelas melayani ekspor
 * per dokumen DAN rekap bulanan), dengan dua hal yang khas laporan:
 *
 * 1. 🔴 TIGA BARIS RINGKAS yang menjadi inti dokumen ini —
 *    `Advance received`, `Reported spending`, dan SELISIHNYA. Baris ketiga
 *    diberi label arah uangnya (REFUND / CLAIM / SETTLED), bukan sekadar
 *    angka: "50.000" tidak memberi tahu siapa membayar siapa, dan itu justru
 *    satu-satunya hal yang perlu dibaca bagian keuangan.
 *
 * 2. `advance_amount` diambil dari KOLOM LAPORAN, bukan dari relasi CA-nya
 *    (D139). Nominal itu dibekukan saat laporan dibuat; membacanya ulang dari
 *    CA akan membuat selisih pada dokumen yang SUDAH ditandatangani berubah
 *    sendiri bila nominal uang mukanya pernah disesuaikan penyetuju.
 */
class CashAdvanceReportDocumentExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    /** Lebar tabel item: No · Date · Description · Receipt No. · Amount · Charged To. */
    private const COLUMNS = 6;

    /** Huruf kolom terakhir. */
    private const LAST_COLUMN = 'F';

    private array $titleRows  = [];
    private array $headerRows = [];
    private array $totalRows  = [];
    private array $itemRanges = [];

    /** [baris judul tanda tangan => jumlah kolomnya] — lebarnya beda per dokumen. */
    private array $signHeadRows = [];

    public function __construct(
        private Collection $reports,
        private CashAdvanceReportService $service,
        private string $sheetTitle = 'Cash Advance Report',
    ) {
    }

    public function title(): string
    {
        return substr(preg_replace('/[\\\\\/\*\?\:\[\]]/', '-', $this->sheetTitle), 0, 31);
    }

    public function array(): array
    {
        $rows = [];
        $line = 0;

        $blank = array_fill(0, self::COLUMNS, '');

        $push = function (array $row) use (&$rows, &$line, $blank) {
            $rows[] = $row + $blank;
            return ++$line;
        };

        foreach ($this->reports as $index => $report) {
            if ($index > 0) {
                $push([]);
                $push([]);
            }

            $this->titleRows[] = $push(['CASH ADVANCE REPORT']);
            $this->titleRows[] = $push([strtoupper($report->cashAdvance?->charged_to_label ?: 'ECLECTIC')]);
            $push([]);

            $push(['No', ':', $report->report_no]);
            $push(['Date', ':', $report->report_date->format('d.m.Y')]);
            $push(['Cash Advance', ':', $report->cashAdvance?->request_no ?? '—']);
            $push(['Requester', ':', $report->employee?->basicData?->nick_name
                ?? $report->employee?->eci ?? '—']);
            $push(['Description', ':', $report->description]);

            if ($report->trashed()) {
                $push(['Deleted', ':', $report->delete_reason ?: '—']);
            }

            $push([]);

            $this->headerRows[] = $push([
                'No.', 'Date', 'Description', 'Receipt No.', 'Amount', 'Charged To',
            ]);

            $firstItem = $line + 1;

            foreach ($report->items as $item) {
                $push([
                    $item->line_no,
                    $item->expense_date?->format('d.m.Y') ?? '',
                    $item->description,
                    $item->receipt_no ?? '',
                    (float) $item->amount,          // 🔴 ANGKA, bukan teks berformat
                    $item->costCenterLabel(),
                ]);
            }

            $this->itemRanges[] = [$firstItem, $line];

            $push([]);

            // 🔴 Tiga baris ringkas — inti dokumen ini.
            $this->totalRows[] = $push(['', '', '', 'Advance received',
                (float) $report->advance_amount, $report->currency]);
            $this->totalRows[] = $push(['', '', '', 'Reported spending',
                (float) $report->reported_amount, $report->currency]);
            $this->totalRows[] = $push(['', '', '', strtoupper($report->settlementLabel()),
                abs((float) $report->difference_amount), $report->currency]);

            $push([]);

            $columns = $this->service->signatureColumns($report);

            $this->signHeadRows[$line + 1] = count($columns);

            $push(array_map(fn ($column) => $column['title'] . ',', $columns));
            $push(array_map(fn ($column) => $column['name'], $columns));
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = self::LAST_COLUMN;

                foreach ($this->titleRows as $row) {
                    $sheet->mergeCells("A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal('center');
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:{$last}{$row}")->getAlignment()->setHorizontal('center');
                    $this->box($sheet, "A{$row}:{$last}{$row}");
                }

                foreach ($this->itemRanges as [$from, $to]) {
                    if ($to < $from) {
                        continue;
                    }

                    $this->box($sheet, "A{$from}:{$last}{$to}");
                    $sheet->getStyle("A{$from}:B{$to}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("E{$from}:E{$to}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("E{$from}:E{$to}")->getAlignment()->setHorizontal('right');
                }

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("D{$row}:{$last}{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal('right');
                    $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("E{$row}")->getAlignment()->setHorizontal('right');
                    $this->box($sheet, "D{$row}:{$last}{$row}");
                }

                foreach ($this->signHeadRows as $row => $count) {
                    $valueRow = $row + 1;
                    $end      = chr(ord('A') + max(0, $count - 1));

                    $sheet->getStyle("A{$row}:{$end}{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:{$end}{$valueRow}")->getAlignment()->setHorizontal('center');
                    $sheet->getRowDimension($valueRow)->setRowHeight(52);
                    $this->box($sheet, "A{$row}:{$end}{$valueRow}");
                }
            },
        ];
    }

    /** Garis tepi tipis mengelilingi seluruh sel dalam rentang. */
    private function box($sheet, string $range): void
    {
        $sheet->getStyle($range)->getBorders()->getAllBorders()->setBorderStyle('thin');
    }
}
