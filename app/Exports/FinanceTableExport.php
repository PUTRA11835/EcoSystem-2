<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

/**
 * Ekspor Excel sederhana untuk laporan keuangan (BPJS, PPh 21): judul kolom + baris.
 * Sel teks yang diawali karakter rumus (= + - @) diberi awalan apostrof agar Excel tidak menjalankannya
 * sebagai formula; angka dikirim sebagai angka.
 */
class FinanceTableExport implements FromArray, WithHeadings, ShouldAutoSize
{
    /** @param string[] $headings @param array<int,array<int,mixed>> $rows */
    public function __construct(private readonly array $headings, private readonly array $rows)
    {
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function array(): array
    {
        return array_map(fn (array $row) => array_map([self::class, 'safe'], $row), $this->rows);
    }

    public static function safe(mixed $v): mixed
    {
        if (is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true)) {
            return "'" . $v;
        }

        return $v;
    }
}
