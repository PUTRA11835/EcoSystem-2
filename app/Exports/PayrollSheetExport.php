<?php

namespace App\Exports;

use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor Excel Payroll (tabel periode & transfer bank). Kolom uang berformat angka `1.000.000,00` (mengikuti bahasa Excel
 * pengguna), kolom teks (nomor karyawan/rekening) dipaksa teks agar nol di depan tidak hilang, dan sel teks yang diawali
 * karakter rumus diamankan dari injeksi formula.
 */
class PayrollSheetExport extends DefaultValueBinder implements FromArray, WithHeadings, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithStyles
{
    /**
     * @param string[]                       $headings
     * @param array<int,array<int,mixed>>    $rows
     * @param string[]                       $moneyCols kolom (huruf) berformat uang
     * @param string[]                       $textCols  kolom (huruf) yang dipaksa teks
     */
    public function __construct(
        private readonly array $headings,
        private readonly array $rows,
        private readonly array $moneyCols = [],
        private readonly array $textCols = [],
    ) {
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return array_map(fn (array $row) => array_map([FinanceTableExport::class, 'safe'], $row), $this->rows);
    }

    public function columnFormats(): array
    {
        $f = [];
        foreach ($this->moneyCols as $c) {
            $f[$c] = '#,##0.00;[Red]-#,##0.00';
        }
        foreach ($this->textCols as $c) {
            $f[$c] = '@';
        }

        return $f;
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        if (in_array(preg_replace('/\d+/', '', $cell->getCoordinate()), $this->textCols, true) && $cell->getRow() > 1 && $value !== null && $value !== '') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
