<?php

namespace App\Exports\Tabular;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Template impor: sheet "Data" (heading + satu baris contoh) dan sheet "Guide"
 * yang menjelaskan tiap kolom — wajib/opsional, format, dan nilai yang diterima.
 */
class TemplateExport implements WithMultipleSheets
{
    /**
     * @param  array<string>        $headings
     * @param  array<int, mixed>    $example
     * @param  array<int, array{0: string, 1: string, 2: string}>  $guide  [kolom, wajib?, keterangan]
     */
    public function __construct(
        private string $title,
        private array $headings,
        private array $example,
        private array $guide,
    ) {
    }

    public function sheets(): array
    {
        return [
            new TabularExport(
                'Data',
                $this->title . ' — Import Template',
                $this->headings,
                [$this->example],
                [],
                ['Fill one row per record. Keep the header row (row 4) unchanged. See the "Guide" sheet for each column.']
            ),
            new class($this->guide) implements FromArray, WithTitle, WithStyles, WithColumnWidths {
                public function __construct(private array $guide)
                {
                }

                public function array(): array
                {
                    return array_merge([['Column', 'Required', 'Notes']], $this->guide);
                }

                public function title(): string
                {
                    return 'Guide';
                }

                public function columnWidths(): array
                {
                    return ['A' => 28, 'B' => 12, 'C' => 90];
                }

                public function styles(Worksheet $sheet): array
                {
                    $sheet->getStyle('A1:C1')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle('A1:C1')->getFill()
                        ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                        ->getStartColor()->setRGB('991B1B');
                    $sheet->getStyle('C:C')->getAlignment()->setWrapText(true);

                    return [];
                }
            },
        ];
    }
}
