<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Export daftar Delivery Support — satu baris per support.
 *
 * Baris sudah disiapkan oleh DeliverySupportController::export(); kelas ini
 * hanya memetakannya ke kolom + merapikan format.
 */
class DeliverySupportExport implements FromArray, WithEvents, WithTitle, WithStrictNullComparison, ShouldAutoSize
{
    // WithStrictNullComparison: tanpa ini Laravel-Excel menulis nilai 0
    // (mis. Total Mandays 0) sebagai sel KOSONG.

    /** Kolom terakhir (12 kolom, lihat header di array()). */
    private const LAST_COL = 'L';

    /** Kolom yang di-center: Type, IO, Total Mandays, Start Date, End Date. */
    private const CENTER_COLS = ['C', 'D', 'E', 'F', 'G'];

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function title(): string
    {
        return 'Delivery Support';
    }

    public function array(): array
    {
        $out = [[
            'Customer',
            'Support Name',
            'Type',
            'IO',
            'Total Mandays',
            'Start Date',
            'End Date',
            'Delivery Owner',
            'Support Manager',
            'Co PM',
            'Support Admin',
            'Sales',
        ]];

        foreach ($this->rows as $r) {
            $out[] = [
                $r['customer'],
                $r['name'],
                $r['type'],
                $r['io_number'],
                $r['total_mandays'],
                $r['start_date'],
                $r['end_date'],
                $r['delivery_owner'],
                $r['support_manager'],
                $r['co_pm'],
                $r['support_admin'],
                $r['sales'],
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
                $lastRow = $this->rows->count() + 1;

                $sheet->getStyle("A1:{$lastCol}1")->applyFromArray([
                    'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                    'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'wrapText' => true],
                ]);

                // Customer + Support Name tetap terlihat saat menggeser ke kanan.
                $sheet->freezePane('C2');

                if ($lastRow <= 1) {
                    return;
                }

                foreach (self::CENTER_COLS as $col) {
                    $sheet->getStyle("{$col}2:{$col}{$lastRow}")
                        ->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                }

                $sheet->getStyle("E2:E{$lastRow}")->getNumberFormat()->setFormatCode('#,##0.##');
            },
        ];
    }
}
