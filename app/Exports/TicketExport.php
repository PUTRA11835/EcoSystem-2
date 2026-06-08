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

class TicketExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    // Column count: A–T (20 columns, Jarvies Status column removed)
    private const LAST_COL = 'T';

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($t) {
            $statusLabel = match ($t['status'] ?? '') {
                'open'                    => 'Open',
                'inprocess'               => 'Inprocess',
                'waiting_on_customer'     => 'Waiting on Customer',
                'waiting_on_3rd_party'    => 'Waiting on 3rd Party',
                'waiting_to_confirmation' => 'Waiting to Confirmation',
                'hold'                    => 'Hold',
                'cancelled'               => 'Cancelled',
                'closed'                  => 'Closed',
                default                   => $t['status'] ?? '-',
            };

            return [
                'ticket_number'            => $t['ticket_number'] ?? '-',
                'description'              => $t['description'] ?? '-',
                'date'                     => $t['created_at']
                    ? \Carbon\Carbon::parse($t['created_at'])->timezone('Asia/Jakarta')->format('d M Y')
                    : '-',
                'customer'                 => $t['customer']['customer_name'] ?? '-',
                'end_customer'             => $t['end_customer_name'] ?? '-',
                'pic'                      => $t['employee']['employee_name'] ?? 'Unassigned',
                'priority'                 => $t['ticket_priority'] ?? '-',
                'scale'                    => $t['scale'] ?? '-',
                'status'                   => $statusLabel,
                'type'                     => $t['ticket_type'] ?? '-',
                'assign_delivery'          => '-',
                'customer_mandays'         => $t['customer_mandays'] !== null
                    ? number_format((float) $t['customer_mandays'], 1)
                    : '-',
                'progress'                 => $t['all_consultant_progress'] > 0
                    ? $t['all_consultant_progress'] . '%'
                    : '-',
                'target_respon_time'       => '-',
                'respon_time'              => '-',
                'respon_time_status'       => '-',
                'target_resolution_time'   => '-',
                'due_date_resolution_time' => $t['end_date']
                    ? \Carbon\Carbon::parse($t['end_date'])->timezone('Asia/Jakarta')->format('d M Y H:i')
                    : '-',
                'resolution_time'          => '-',
                'resolution_time_status'   => '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Tiket',
            'Description',
            'Date',
            'Customer',
            'End Customer',
            'PIC',
            'Priority',
            'Scale',
            'Status',
            'Type',
            'Assign Delivery',
            'Customer Mandays',
            'Progress',
            'Target Respon Time (Hour)',
            'Respon Time (Hour)',
            'Respon Time Status',
            'Target Resolution Time',
            'Due Date/Time Resolution Time',
            'Resolution Time',
            'Resolution Time Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->rows->count() + 1;
        $lastCol = self::LAST_COL;

        $styles = [
            1 => [
                'font'      => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFCC0000']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            ],
        ];

        for ($i = 2; $i <= $lastRow; $i++) {
            $rowArgb = ($i % 2 === 0) ? 'FFF9FAFB' : 'FFFFFFFF';
            $styles["A{$i}:{$lastCol}{$i}"] = [
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => $rowArgb]],
            ];
        }

        return $styles;
    }
}
