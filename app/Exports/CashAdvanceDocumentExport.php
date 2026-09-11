<?php

namespace App\Exports;

use App\Services\CashAdvance\CashAdvanceService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;

/**
 * Ekspor dokumen Cash Advance — bentuk DOKUMEN, bukan tabel rekap.
 *
 * SATU KELAS UNTUK DUA KEBUTUHAN, seperti dua sub-modul sebelumnya (D114).
 * Ekspor per dokumen dan "Monthly Export" bukan dua format berbeda: yang
 * bulanan adalah blok yang sama, diulang untuk tiap dokumen pada bulan itu,
 * dipisah dua baris kosong. Dua kelas berarti dua tata letak yang harus dijaga
 * tetap sama — dan cepat atau lambat salah satunya berubah sendiri.
 *
 * Tata letaknya mengikuti `print.blade.php`, supaya berkas Excel dan kertas
 * cetakan dapat ditumpuk dan cocok.
 *
 * ── TIGA HAL YANG KHAS DOKUMEN INI ────────────────────────────────────────
 *
 * 1. 🔴 NOMINALNYA DITULIS SEBAGAI ANGKA, bukan teks berformat. "350.000"
 *    yang disimpan sebagai teks tidak dapat dijumlahkan penerimanya, dan
 *    berkas keuangan yang kolomnya tidak bisa dijumlahkan kehilangan sebagian
 *    besar gunanya (pelajaran D47). Pemformatannya diserahkan ke number format
 *    Excel, di registerEvents().
 *
 * 2. DUA SUMBU KEADAAN (D140). `Status` dan `Settlement` adalah kolom
 *    TERPISAH, dan itu bukan pengulangan: sebuah CA dapat berstatus
 *    `Approved` sekaligus bersifat `Outstanding`. Menggabungkannya jadi satu
 *    kolom akan menyembunyikan uang yang sudah keluar tetapi belum
 *    dipertanggungjawabkan — justru angka yang paling perlu dilihat.
 *
 * 3. JUMLAH KOLOM TANDA TANGAN TIDAK TETAP (D129/D137). Kolomnya diturunkan
 *    dari langkah alur milik tiap dokumen, jadi dua dokumen dalam satu berkas
 *    bulanan bisa punya jumlah kolom berbeda — dan itu benar, karena alur
 *    persetujuan memang dapat berubah di antara keduanya. Lebar bloknya
 *    dihitung per dokumen, bukan dipatok.
 */
class CashAdvanceDocumentExport implements FromArray, WithEvents, WithTitle, ShouldAutoSize
{
    /** Lebar blok: No · Description · Curr · Amount · Status · Settlement. */
    private const COLUMNS = 6;

    /** Huruf kolom terakhir. */
    private const LAST_COLUMN = 'F';

    /** Baris (1-indexed) yang perlu diberi gaya, dikumpulkan saat menyusun array. */
    private array $titleRows  = [];
    private array $headerRows = [];
    private array $amountRows = [];

    /** [baris judul tanda tangan => jumlah kolomnya] — lebarnya beda per dokumen. */
    private array $signHeadRows = [];

    public function __construct(
        private Collection $requests,
        private CashAdvanceService $service,
        private string $sheetTitle = 'Cash Advance',
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

            $this->titleRows[] = $push(['CASH ADVANCE']);
            $this->titleRows[] = $push([strtoupper($this->service->documentHeading($request))]);
            $push([]);

            $push(['No', ':', $request->request_no]);
            $push(['Date', ':', $request->dateRangeLabel()
                ? $request->request_date->format('d.m.Y') . ' — ' . $request->dateRangeLabel()
                : $request->request_date->format('d.m.Y')]);
            $push(['Requester', ':', $request->employee?->basicData?->nick_name
                ?? $request->employee?->eci ?? '—']);
            $push(['Charged To', ':', $request->charged_to_label ?? '—']);

            // Dokumen yang dihapus TETAP diekspor, dan alasannya ikut tertulis.
            // Itulah gunanya soft delete (D109): jejaknya harus terbaca, bukan
            // hanya tersimpan.
            if ($request->trashed()) {
                $push(['Deleted', ':', $request->delete_reason ?: '—']);
            }

            $push([]);

            $this->headerRows[] = $push([
                'No.', 'Description', 'Curr', 'Amount', 'Status', 'Settlement',
            ]);

            $this->amountRows[] = $push([
                1,
                $request->description,
                $request->currency,
                (float) $request->amount,          // 🔴 ANGKA, bukan teks berformat
                $request->statusLabel(),
                $request->settlementLabel(),
            ]);

            if ($request->notes) {
                $push([]);
                $push(['Notes', ':', $request->notes]);
            }

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

                foreach ($this->amountRows as $row) {
                    $this->box($sheet, "A{$row}:{$last}{$row}");
                    $sheet->getStyle("A{$row}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("C{$row}")->getAlignment()->setHorizontal('center');
                    $sheet->getStyle("E{$row}:{$last}{$row}")->getAlignment()->setHorizontal('center');

                    // Pemisah ribuan titik, desimal koma — sama dengan yang
                    // dibaca dan ditulis modul ini di layar.
                    $sheet->getStyle("D{$row}")->getNumberFormat()->setFormatCode('#,##0');
                    $sheet->getStyle("D{$row}")->getAlignment()->setHorizontal('right');
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
