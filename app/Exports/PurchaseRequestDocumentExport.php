<?php

namespace App\Exports;

use App\Models\PurchaseRequest\PurchaseRequestItem;
use App\Services\PurchaseRequest\PurchaseRequestService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Ekspor dokumen Purchase Request — bentuk DOKUMEN, bukan tabel rekap.
 *
 * SATU KELAS UNTUK DUA KEBUTUHAN. Ekspor per dokumen dan "Monthly Export" bukan
 * dua format berbeda: yang bulanan adalah blok yang sama, diulang untuk setiap
 * dokumen pada bulan itu, dipisah dua baris kosong (Keputusan D114). Membuat dua
 * kelas berarti dua tata letak yang harus dijaga tetap sama — dan cepat atau
 * lambat salah satunya berubah sendiri.
 *
 * Tata letaknya sama persis dengan halaman cetak, supaya keduanya dapat
 * ditumpuk dan cocok.
 *
 * ── DUA HAL YANG BERBEDA DARI ReimbursementDocumentExport ──────────────────
 *
 * 1. NOL KOLOM NOMINAL. Dokumen ini berhenti di kuantitas + satuan; harga baru
 *    muncul di Purchase Order. Yang ditulis sebagai ANGKA (bukan teks) adalah
 *    kolom Qty — supaya tetap dapat dijumlahkan di Excel bila penerima
 *    berkasnya membutuhkannya (pelajaran yang sama dengan D47).
 *
 * 2. JUMLAH KOLOM TANDA TANGAN TIDAK TETAP (Keputusan D129). Kolomnya
 *    diturunkan dari langkah alur milik tiap dokumen, jadi dua dokumen dalam
 *    satu berkas bulanan bisa punya jumlah kolom yang berbeda — dan itu benar,
 *    karena alur persetujuan memang dapat berubah di antara keduanya.
 *    Karena itu lebar bloknya dihitung per dokumen, bukan dipatok.
 */
class PurchaseRequestDocumentExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    /** Lebar tabel item: No · Description · Qty · UoM · Use Date · Period · Charged To. */
    private const COLUMNS = 7;

    /** Huruf kolom terakhir tabel item. */
    private const LAST_COLUMN = 'G';

    /** Baris (1-indexed) yang perlu diberi gaya, dikumpulkan saat menyusun array. */
    private array $titleRows  = [];
    private array $headerRows = [];
    private array $totalRows  = [];
    private array $itemRanges = [];

    /** [baris judul tanda tangan => jumlah kolomnya] — lebarnya beda per dokumen. */
    private array $signHeadRows = [];

    public function __construct(
        private Collection $requests,
        private PurchaseRequestService $service,
        private string $sheetTitle = 'Purchase Request',
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

        $blank = array_fill(0, self::COLUMNS, '');

        $push = function (array $row) use (&$rows, &$line, $blank) {
            $rows[] = $row + $blank;              // dipadatkan ke lebar tetap
            return ++$line;                       // nomor baris 1-indexed
        };

        foreach ($this->requests as $index => $request) {
            if ($index > 0) {
                $push([]);
                $push([]);
            }

            $this->titleRows[] = $push(['PURCHASE REQUEST']);
            $this->titleRows[] = $push([strtoupper($this->service->documentHeading($request))]);
            $push([]);

            $push(['No', ':', $request->request_no]);
            $push(['Date', ':', $request->request_date->format('d.m.Y')]);
            $push(['Summary', ':', $request->title]);
            $push(['Charged To', ':', $request->charged_to_label ?? '—']);
            $push([]);

            $this->headerRows[] = $push([
                'No.', 'Item Description', 'Qty', 'UoM', 'Use Date', 'Period', 'Charged To',
            ]);

            $firstItem = $line + 1;

            foreach ($request->items as $item) {
                $push([
                    $item->line_no,
                    $item->description,
                    (float) $item->qty,           // ANGKA, bukan teks berformat
                    $item->unit,
                    $item->useDateLabel(),
                    $item->periodLabel(),
                    $item->costCenterLabel(),
                ]);
            }

            $this->itemRanges[] = [$firstItem, $line];

            $this->totalRows[] = $push(['', '', '', '', '', 'Total Items', $request->item_count]);
            $this->totalRows[] = $push(['', '', '', '', '', 'Total Qty', $request->qtySummaryLabel()]);

            $push([]);

            // Kolom tanda tangan diturunkan dari langkah alur dokumen ini.
            $columns = $this->service->signatureColumns($request);

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
                    $sheet->getStyle("A{$from}:A{$to}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("C{$from}:E{$to}")->getAlignment()->setHorizontal('center');

                    // Kuantitas: dua desimal hanya bila memang ada — "2" tetap
                    // terbaca "2", bukan "2,00" yang membuatnya mirip nominal.
                    $sheet->getStyle("C{$from}:C{$to}")->getNumberFormat()->setFormatCode('#,##0.##');
                }

                foreach ($this->totalRows as $row) {
                    $sheet->getStyle("F{$row}:{$last}{$row}")->getFont()->setBold(true);
                    $sheet->getStyle("F{$row}")->getAlignment()->setHorizontal('right');
                    $this->box($sheet, "F{$row}:{$last}{$row}");
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
