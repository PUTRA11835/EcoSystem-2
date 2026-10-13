<?php

namespace App\Exports\Tabular;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Template Excel tunggal untuk seluruh ekspor tabel HR & General.
 *
 * Tata letak: judul (baris 1), keterangan filter/tanggal (baris 2), baris
 * kosong, lalu baris heading (baris 4) dan data mulai baris 5. Heading diberi
 * warna, dibekukan, dan diberi filter. Importer mencari baris heading sendiri,
 * jadi berkas hasil ekspor dapat diedit lalu diimpor kembali.
 */
class TabularExport implements FromArray, WithTitle, WithEvents
{
    public const HEADER_ROW = 4;
    private const MAX_WIDTH = 60;

    /**
     * @param  array<string>             $headings
     * @param  array<int, array<mixed>>  $rows
     * @param  array<int, string>        $formats  index kolom (0-based) => int|money|date
     * @param  array<string>             $meta
     */
    public function __construct(
        private string $sheetTitle,
        private string $title,
        private array $headings,
        private array $rows,
        private array $formats = [],
        private array $meta = [],
    ) {
    }

    public function array(): array
    {
        $out = [[$this->title], [implode('   |   ', $this->meta)], [null], $this->headings];

        foreach ($this->rows as $row) {
            $out[] = array_values($row);
        }

        return $out;
    }

    public function title(): string
    {
        return substr(preg_replace('/[\\\\\/\*\?\:\[\]]/', '-', $this->sheetTitle), 0, 31);
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet    = $event->sheet->getDelegate();
                $colCount = max(1, count($this->headings));
                $lastCol  = Coordinate::stringFromColumnIndex($colCount);
                $header   = self::HEADER_ROW;
                $first    = $header + 1;
                $last     = $header + count($this->rows);

                $sheet->mergeCells("A1:{$lastCol}1");
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);

                $sheet->mergeCells("A2:{$lastCol}2");
                $sheet->getStyle('A2')->getFont()->setItalic(true)->getColor()->setRGB('6B7280');

                $headerStyle = $sheet->getStyle("A{$header}:{$lastCol}{$header}");
                $headerStyle->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $headerStyle->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('991B1B');
                $headerStyle->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setWrapText(true);
                $sheet->getRowDimension($header)->setRowHeight(26);

                if ($last >= $first) {
                    $body = $sheet->getStyle("A{$first}:{$lastCol}{$last}");
                    $body->getBorders()->getAllBorders()
                        ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D1D5DB');
                    $body->getAlignment()->setVertical(Alignment::VERTICAL_TOP);

                    foreach ($this->formats as $index => $format) {
                        $col  = Coordinate::stringFromColumnIndex($index + 1);
                        $code = match ($format) {
                            'int'   => '#,##0',
                            'money' => '#,##0.00',
                            default => null,
                        };

                        if ($code) {
                            $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                                ->getNumberFormat()->setFormatCode($code);
                            $sheet->getStyle("{$col}{$first}:{$col}{$last}")
                                ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                        }
                    }
                }

                $sheet->getStyle("A{$header}:{$lastCol}{$header}")->getBorders()->getAllBorders()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('7F1D1D');

                $sheet->freezePane('A' . $first);
                $sheet->setAutoFilter("A{$header}:{$lastCol}" . max($last, $header));

                foreach ($this->headings as $i => $heading) {
                    $width = mb_strlen((string) $heading);

                    foreach (array_slice($this->rows, 0, 300) as $row) {
                        $cell  = array_values($row)[$i] ?? '';
                        $width = max($width, min(mb_strlen((string) $cell), self::MAX_WIDTH));
                    }

                    $col = Coordinate::stringFromColumnIndex($i + 1);
                    $sheet->getColumnDimension($col)->setWidth(min(max($width + 3, 10), self::MAX_WIDTH));
                    $sheet->getStyle("{$col}{$first}:{$col}" . max($last, $first))
                        ->getAlignment()->setWrapText(true);
                }
            },
        ];
    }
}
