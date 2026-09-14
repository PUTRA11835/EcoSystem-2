<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

class CustomerMdExport implements FromCollection, WithHeadings, WithStyles, ShouldAutoSize
{
    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows->map(fn ($r) => [
            'ticket_number' => $r['ticket_number'],
            'description'   => $r['description'],
            'ticket_type'   => $r['ticket_type'],
            'customer_name' => $r['customer_name'] ?? '-',
            'delivery_name' => $r['delivery_name'] ?? '-',
            'lead_name'     => $r['lead_name'] ?? 'Unassigned',
            'md_status'     => $r['md_status_label'],
            'created_at'    => $r['created_at'] ? $r['created_at']->timezone('Asia/Jakarta')->format('d/m/Y') : '',
        ]);
    }

    public function headings(): array
    {
        return ['Ticket Number', 'Description', 'Ticket Type', 'Customer', 'Delivery', 'Ticket Lead', 'Customer MD Status', 'Created At'];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;
        $lastCol = 'H';

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];

        for ($i = 2; $i <= $lastRow; $i++) {
            $argb = (($i % 2) === 0) ? 'FFE2EFDA' : 'FFFFFFFF';
            $styles["A{$i}:{$lastCol}{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $argb]],
            ];
            foreach (['C', 'G', 'H'] as $col) {
                $styles["{$col}{$i}"] = ['alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER]];
            }
        }

        return $styles;
    }
}
