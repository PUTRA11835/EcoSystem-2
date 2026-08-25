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

/**
 * Mirrors the on-screen Support timesheet table columns, plus one export-only
 * column (Delivery Support) that isn't shown in the live view.
 */
class TimesheetSupportExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    private const LAST_COL = 'O';

    protected Collection $rows;
    protected array $jatahMap;
    protected Collection $deliveryMap;

    public function __construct(Collection $rows, array $jatahMap, Collection $deliveryMap)
    {
        $this->rows        = $rows;
        $this->jatahMap    = $jatahMap;
        $this->deliveryMap = $deliveryMap;
    }

    public function collection(): Collection
    {
        return $this->rows->map(function ($ts) {
            $employeeName = trim(($ts->employee?->basicData?->first_name ?? '') . ' ' . ($ts->employee?->basicData?->last_name ?? ''));
            $jatahKey     = $ts->ticket_id . '_' . $ts->employee_id;

            return [
                'employee'           => $employeeName ?: '-',
                'date'               => $ts->date?->format('d M Y') ?? '-',
                'activity_date'      => $ts->activity_date?->format('d M Y') ?? '-',
                'month'              => $ts->period_month ?? '-',
                'year'               => $ts->period_year  ?? '-',
                'status'             => ucfirst($ts->status ?? '-'),
                'ticket'             => $ts->ticket?->ticket_number ?? ($ts->ticket_id ? "#{$ts->ticket_id}" : '-'),
                'ticket_description' => $ts->ticket?->description ?? '-',
                'customer'           => $ts->ticket?->customer?->basicData?->name_1 ?? '-',
                'type'               => $ts->ticket_id ? (($ts->ticket?->ticket_type === 'Internal') ? 'Internal' : 'Non Internal') : '-',
                'delivery_support'   => $ts->ticket_id ? ($this->deliveryMap[$ts->ticket_id] ?? '-') : '-',
                'quota_md'           => $ts->ticket_id ? ($this->jatahMap[$jatahKey] ?? '-') : '-',
                'activity'           => $ts->description ?? '-',
                'md_consumed'        => $ts->md_consumed ?? '-',
                'on_site'            => $ts->presence === 'onsite' ? 'X' : '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Employee',
            'Date',
            'Activity Date',
            'Month',
            'Year',
            'Status',
            'Ticket',
            'Description',
            'Customer',
            'Type',
            'Delivery Support',
            'Quota MD',
            'Activity',
            'MD Consumed',
            'On Site',
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
