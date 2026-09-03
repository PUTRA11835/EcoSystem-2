<?php

namespace App\Exports;

use App\Models\DeliverySupport;
use App\Models\DeliverySupportRecons;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Export detail tiket sebuah batch Recons (Delivery Support) ke Excel.
 *
 * Susunan sheet:
 *   1. Judul dokumen + nama support/customer
 *   2. Blok informasi batch (nomor, tanggal, status, pembuat, penyubmit)
 *   3. Tabel detail tiket + baris Total MD
 *
 * Catatan warna (mengikuti palet aplikasi, bukan warna acak):
 * - Header tabel  : merah tua brand #991B1B + teks putih (sama dengan warna
 *                   tab aktif & tombol primary di UI).
 * - Baris selang-seling #F9FAFB (abu paling muda Tailwind) supaya baris panjang
 *   tetap mudah diikuti tanpa membuat tabel jadi ramai.
 * - Garis tabel #E5E7EB (abu muda) — tipis, tidak mendominasi isi.
 * - Badge status memakai warna semantik: hijau untuk Submitted, kuning untuk
 *   Draft; konsisten dengan badge di halaman web.
 * Kontras teks terhadap latar semuanya di atas rasio 4.5:1 (WCAG AA).
 */
class DeliverySupportReconsExport implements FromArray, WithEvents, WithTitle
{
    private const LAST_COL = 'G';

    /** Judul kolom tabel — dipakai untuk menulis DAN menemukan baris header. */
    private const TABLE_HEADINGS = [
        'Ticket Number', 'Description', 'Start Date', 'Close Date',
        'Status', 'Type', 'Customer MD',
    ];

    // Palet (ARGB) — diselaraskan dengan warna UI aplikasi.
    private const C_BRAND       = 'FF991B1B'; // merah tua brand (header tabel)
    private const C_WHITE       = 'FFFFFFFF';
    private const C_TEXT        = 'FF111827'; // gray-900
    private const C_TEXT_MUTED  = 'FF6B7280'; // gray-500
    private const C_BORDER      = 'FFE5E7EB'; // gray-200
    private const C_STRIPE      = 'FFF9FAFB'; // gray-50
    private const C_TOTAL_BG    = 'FFFEF3C7'; // amber-100 (baris total)
    private const C_GREEN_TEXT  = 'FF166534';
    private const C_AMBER_TEXT  = 'FF92400E';

    public function __construct(
        private DeliverySupport $support,
        private DeliverySupportRecons $recons,
        private Collection $rows,
        private ?string $createdByName = null,
        private ?string $submittedByName = null,
    ) {
    }

    public function title(): string
    {
        return 'Recons Detail';
    }

    public function array(): array
    {
        $blank = array_fill(0, count(self::TABLE_HEADINGS), '');

        $out = [
            ['TICKET RECONCILIATION', '', '', '', '', '', ''],
            [
                ($this->support->name ?: ('Support #' . $this->support->id))
                    . ' — ' . ($this->support->client->basicData->name_1 ?? 'N/A'),
                '', '', '', '', '', '',
            ],
            $blank,
            ['Recons Number', $this->recons->recons_number,          '', '', '', '', ''],
            ['Description',   $this->recons->description ?: '-',     '', '', '', '', ''],
            ['Recons Date',   $this->recons->recons_date?->format('d M Y') ?: '-', '', '', '', '', ''],
            ['Status',        $this->recons->status_label,           '', '', '', '', ''],
            ['Created By',    $this->createdByName ?: '-',           '', '', '', '', ''],
            ['Created Date',  $this->recons->created_at?->format('d M Y H:i') ?: '-', '', '', '', '', ''],
            ['Submitted By',  $this->submittedByName ?: '-',         '', '', '', '', ''],
            ['Submitted At',  $this->recons->submitted_at?->format('d M Y H:i') ?: '-', '', '', '', '', ''],
            $blank,
            self::TABLE_HEADINGS,
        ];

        foreach ($this->rows as $row) {
            $out[] = [
                $row['ticket_number'] ?: ('#' . $row['ticket_id']),
                $row['description'] ?: '-',
                $row['start_date_label'],
                $row['close_date_label'],
                $row['status_label'],
                $row['type'] ?: '-',
                $row['man_days'] !== null ? (float) $row['man_days'] : null,
            ];
        }

        $out[] = ['', '', '', '', '', 'Total MD', (float) $this->rows->sum(fn ($r) => (float) ($r['man_days'] ?? 0))];

        return $out;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last  = self::LAST_COL;

                // Baris header dicari dari isi sel, BUKAN dihitung dari jumlah
                // elemen array: baris kosong tidak selalu menempati baris di
                // sheet, sehingga perhitungan manual bisa meleset satu baris
                // (dulu membuat baris data ikut terwarnai seperti header).
                $headerRow = $this->findHeaderRow($sheet);
                $firstData = $headerRow + 1;
                $lastRow   = $sheet->getHighestRow();
                $totalRow  = $lastRow;
                $dataEnd   = $totalRow - 1;

                $this->styleColumns($sheet);
                $this->styleTitle($sheet, $last);
                $this->styleInfoBlock($sheet, $headerRow);
                $this->styleTableHeader($sheet, $headerRow, $last);

                if ($dataEnd >= $firstData) {
                    $this->styleDataRows($sheet, $firstData, $dataEnd, $last);
                }

                $this->styleTotalRow($sheet, $totalRow, $last);

                // Header tetap terlihat saat menggulir + filter siap pakai.
                $sheet->freezePane("A{$firstData}");
                if ($dataEnd >= $firstData) {
                    $sheet->setAutoFilter("A{$headerRow}:{$last}{$dataEnd}");
                }

                $sheet->setSelectedCell('A1');
            },
        ];
    }

    // ── styling helpers ─────────────────────────────────────────────────────

    /** Cari baris yang kolom A-nya berisi judul kolom pertama tabel. */
    private function findHeaderRow(Worksheet $sheet): int
    {
        $needle = self::TABLE_HEADINGS[0];

        for ($row = 1; $row <= min($sheet->getHighestRow(), 50); $row++) {
            if ((string) $sheet->getCell('A' . $row)->getValue() === $needle) {
                return $row;
            }
        }

        return 12; // fallback aman: posisi header pada susunan array saat ini
    }

    /** Lebar kolom ditetapkan eksplisit supaya proporsinya terkontrol. */
    private function styleColumns(Worksheet $sheet): void
    {
        foreach ([
            'A' => 18,  // Ticket Number
            'B' => 52,  // Description
            'C' => 14,  // Start Date
            'D' => 14,  // Close Date
            'E' => 13,  // Status
            'F' => 20,  // Type
            'G' => 14,  // Customer MD
        ] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    private function styleTitle(Worksheet $sheet, string $last): void
    {
        $sheet->mergeCells("A1:{$last}1");
        $sheet->mergeCells("A2:{$last}2");

        $sheet->getStyle('A1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => self::C_BRAND]],
        ]);
        $sheet->getStyle('A2')->applyFromArray([
            'font' => ['size' => 10, 'color' => ['argb' => self::C_TEXT_MUTED]],
        ]);

        $sheet->getRowDimension(1)->setRowHeight(22);
    }

    /** Blok informasi batch: label abu di kolom A, nilai tebal di kolom B. */
    private function styleInfoBlock(Worksheet $sheet, int $headerRow): void
    {
        // Baris info berada di antara judul (baris 1-2) dan header tabel.
        for ($row = 4; $row < $headerRow - 1; $row++) {
            $sheet->getStyle("A{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::C_TEXT_MUTED]],
            ]);
            $sheet->getStyle("B{$row}")->applyFromArray([
                'font' => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::C_TEXT]],
            ]);

            // Nilai boleh melebar melewati kolom B (mis. deskripsi panjang).
            $sheet->getStyle("B{$row}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

            // Status diberi warna semantik seperti badge di halaman web.
            if ((string) $sheet->getCell("A{$row}")->getValue() === 'Status') {
                $isSubmitted = $this->recons->isSubmitted();
                $sheet->getStyle("B{$row}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => [
                        'argb' => $isSubmitted ? self::C_GREEN_TEXT : self::C_AMBER_TEXT,
                    ]],
                ]);
            }
        }
    }

    private function styleTableHeader(Worksheet $sheet, int $headerRow, string $last): void
    {
        $sheet->getStyle("A{$headerRow}:{$last}{$headerRow}")->applyFromArray([
            'font'      => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::C_WHITE]],
            'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::C_BRAND]],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical'   => Alignment::VERTICAL_CENTER,
            ],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::C_BRAND]]],
        ]);

        $sheet->getRowDimension($headerRow)->setRowHeight(24);
    }

    private function styleDataRows(Worksheet $sheet, int $firstData, int $dataEnd, string $last): void
    {
        $range = "A{$firstData}:{$last}{$dataEnd}";

        $sheet->getStyle($range)->applyFromArray([
            'font'      => ['size' => 10, 'color' => ['argb' => self::C_TEXT]],
            'borders'   => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => self::C_BORDER]]],
            'alignment' => ['vertical' => Alignment::VERTICAL_CENTER],
        ]);

        // Perataan per kolom: teks kiri, tanggal/status/tipe tengah, angka kanan.
        $sheet->getStyle("A{$firstData}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B{$firstData}:B{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
        $sheet->getStyle("B{$firstData}:B{$dataEnd}")->getAlignment()->setWrapText(true);
        $sheet->getStyle("C{$firstData}:F{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("G{$firstData}:G{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$firstData}:G{$dataEnd}")->getNumberFormat()->setFormatCode('0.00');

        // Zebra striping supaya baris panjang mudah diikuti mata.
        for ($row = $firstData; $row <= $dataEnd; $row++) {
            if (($row - $firstData) % 2 === 1) {
                $sheet->getStyle("A{$row}:{$last}{$row}")->applyFromArray([
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::C_STRIPE]],
                ]);
            }
        }
    }

    private function styleTotalRow(Worksheet $sheet, int $totalRow, string $last): void
    {
        $sheet->getStyle("A{$totalRow}:{$last}{$totalRow}")->applyFromArray([
            'font'    => ['bold' => true, 'size' => 10, 'color' => ['argb' => self::C_TEXT]],
            'fill'    => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => self::C_TOTAL_BG]],
            'borders' => [
                'top'    => ['borderStyle' => Border::BORDER_THIN,   'color' => ['argb' => self::C_BORDER]],
                'bottom' => ['borderStyle' => Border::BORDER_DOUBLE, 'color' => ['argb' => self::C_BRAND]],
            ],
        ]);

        $sheet->getStyle("F{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
        $sheet->getStyle("G{$totalRow}")->getNumberFormat()->setFormatCode('0.00');
    }
}
