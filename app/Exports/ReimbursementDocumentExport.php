<?php

namespace App\Exports;

use App\Models\Reimbursement\ReimbursementRequest;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Ekspor dokumen reimbursement — bentuk DOKUMEN, bukan tabel rekap.
 *
 * SATU KELAS UNTUK DUA KEBUTUHAN. Ekspor per dokumen dan "Monthly Export" bukan
 * dua format berbeda: yang bulanan adalah blok yang sama, diulang untuk setiap
 * dokumen pada bulan itu, dipisah dua baris kosong (Keputusan D114). Membuat dua
 * kelas berarti dua tata letak yang harus dijaga tetap sama — dan cepat atau
 * lambat salah satunya berubah sendiri.
 *
 * Tata letaknya mengikuti berkas acuan (reimbursement_2026-08.xlsx) dan sama
 * persis dengan halaman cetak, supaya keduanya dapat ditumpuk dan cocok.
 *
 * NOMINAL DITULIS SEBAGAI ANGKA, bukan teks berformat. Kolom uang yang berisi
 * teks tidak dapat dijumlahkan di Excel sama sekali — pelajaran yang sama dengan
 * Keputusan D47 pada rekap absensi.
 */
class ReimbursementDocumentExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    /** Baris (1-indexed) yang perlu diberi gaya, dikumpulkan saat menyusun array. */
    private array $titleRows   = [];
    private array $headerRows  = [];
    private array $groupRows   = [];
    private array $totalRows   = [];
    private array $signHeadRows = [];
    private array $itemRanges  = [];

    public function __construct(
        private Collection $requests,
        private ReimbursementService $service,
        private string $sheetTitle = 'Reimbursement',
    ) {
    }

    public function title(): string
    {
        // Nama sheet Excel maksimal 31 karakter dan menolak beberapa tanda baca.
        return substr(preg_replace('/[\\\\\/\*\?\:\[\]]/', '-', $this->sheetTitle), 0, 31);
    }

    public function array(): array
    {
        $rows = [];
        $line = 0;

        $push = function (array $row) use (&$rows, &$line) {
            $rows[] = $row;
            return ++$line;                       // nomor baris 1-indexed
        };

        foreach ($this->requests as $index => $request) {
            if ($index > 0) {
                $push(['', '', '', '', '']);
                $push(['', '', '', '', '']);
            }

            $this->titleRows[]  = $push(['REIMBURSEMENT', '', '', '', '']);
            $this->titleRows[]  = $push([strtoupper($this->service->documentHeading($request)), '', '', '', '']);
            $push(['', '', '', '', '']);

            $push(['No', ':', $request->request_no, '', '']);
            $push(['Date', ':', $request->request_date->format('d.m.Y'), '', '']);
            $push(['', '', '', '', '']);

            $this->headerRows[] = $push(['No.', 'Description', 'Receipt No.', 'Curr', 'Amount']);
            $this->groupRows[]  = $push(['', $request->title, '', '', '']);

            $firstItem = $line + 1;

            foreach ($request->items as $item) {
                $push([
                    $item->line_no,
                    $item->exportDescription(),
                    $item->receipt_no ?? '',
                    $item->currency,
                    (float) $item->amount,
                ]);
            }

            $this->itemRanges[] = [$firstItem, $line];

            $this->totalRows[] = $push(['', '', '', 'Total', (float) $request->total_amount]);

            $push(['', '', '', '', '']);

            $signatories = $this->service->signatories($request);

            $this->signHeadRows[] = $push(['Requester,', 'Accounting,', 'Cashier,', 'Approved by,', '']);
            $push([
                $signatories['requester'],
                $signatories['accounting'],
                $signatories['cashier'],
                $signatories['approver'],
                '',
            ]);
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();

                foreach ($this->titleRows as $row) {
                    $sheet->mergeCells("A{$row}:E{$row}");
                    $sheet->getStyle("A{$row}")->getFont()->setBold(true)->setSize(13);
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal('center');
                }

                foreach ($this->headerRows as $row) {
                    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:E{$row}")->getAlignment()->setHorizontal('center');
                    $this->box($sheet, "A{$row}:E{$row}");
                }

                foreach ($this->groupRows as $row) {
                    $sheet->mergeCells("B{$row}:E{$row}");
                    $sheet->getStyle("A{$row}:E{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal('center');
                    $this->box($sheet, "A{$row}:E{$row}");
                }

                foreach ($this->itemRanges as [$from, $to]) {
                    if ($to < $from) {
                        continue;
                    }

                    $this->box($sheet, "A{$from}:E{$to}");
                    $sheet->getStyle("A{$from}:A{$to}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("C{$from}:D{$to}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("E{$from}:E{$to}")->getNumberFormat()->setFormatCode('#,##0.00');
                }

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("D{$row}:E{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal('right');
                    $sheet->getStyle("E{$row}")->getNumberFormat()->setFormatCode('#,##0.00');
                    $this->box($sheet, "A{$row}:E{$row}");
                }

                foreach ($this->signHeadRows as $row) {
                    $valueRow = $row + 1;
                    $sheet->getStyle("A{$row}:D{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("A{$row}:D{$valueRow}")->getAlignment()->setHorizontal('center');
                    $sheet->getRowDimension($valueRow)->setRowHeight(52);
                    $this->box($sheet, "A{$row}:D{$valueRow}");
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
