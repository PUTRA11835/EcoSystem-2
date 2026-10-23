<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;

/**
 * Export list tiket. Urutan & nama kolom mengikuti tabel di ticket/index.blade.php
 * (kecuali kolom aksi di layar: SLA Report & Actions).
 */
class TicketExport implements
    FromCollection,
    WithHeadings,
    WithStyles,
    ShouldAutoSize
{
    // Column count: A–Y (25 columns, lihat headings())
    private const LAST_COL = 'Y';

    protected Collection $rows;

    public function __construct(Collection $rows)
    {
        $this->rows = $rows;
    }

    private static function date($value, string $format = 'd M Y'): string
    {
        return $value ? Carbon::parse($value)->timezone('Asia/Jakarta')->format($format) : '-';
    }

    private static function hours($value): string
    {
        return $value !== null ? number_format((float) $value, 2) : '-';
    }

    private static function slaStatus(?string $status): string
    {
        return match ($status) {
            'met'                => 'Met',
            'breached'           => 'Breached',
            'pending'            => 'Pending',
            'pending_validation' => 'Validating',
            'paused'             => 'Paused',
            null, ''            => '-',
            default              => $status,
        };
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

            // Day on Close: di list, tiket closed/cancelled menampilkan labelnya
            // (bukan angka) — ikuti persis supaya Excel sama dengan layar.
            $dayOnClose = match ($t['status'] ?? '') {
                'closed'    => 'Closed',
                'cancelled' => 'Cancelled',
                default     => $t['day_on_close'] ?? '-',
            };

            $sla = $t['sla'] ?? null;

            return [
                'last_update'              => self::date($t['last_update'] ?? null, 'd M Y H:i'),
                'ticket_number'            => $t['ticket_number'] ?? '-',
                'description'              => $t['description'] ?? '-',
                'start_date'               => self::date($t['created_at'] ?? null),
                'close_date'               => self::date($t['closed_at'] ?? null),
                'day_on_close'             => $dayOnClose,
                'customer'                 => $t['customer']['customer_name'] ?? '-',
                'end_customer'             => $t['end_customer_name'] ?? '-',
                'pic'                      => $t['employee']['employee_name'] ?? 'Unassigned',
                'priority'                 => $t['ticket_priority'] ?? '-',
                'scale'                    => $t['scale'] ?? '-',
                'status'                   => $statusLabel,
                'type'                     => $t['ticket_type'] ?? '-',
                'module'                   => $t['module'] ?? '-',
                'assign_delivery'          => $t['assign_delivery'] ?? 'Unassigned',
                'customer_mandays'         => $t['customer_mandays'] !== null
                    ? number_format((float) $t['customer_mandays'], 1)
                    : '-',
                'activity_date'            => self::date($t['activity_date'] ?? null),
                'progress'                 => $t['all_consultant_progress'] > 0
                    ? $t['all_consultant_progress'] . '%'
                    : '-',
                'target_respon_time'       => self::hours($sla['target_response_hours'] ?? null),
                'respon_time'              => self::hours($sla['response_time_hours'] ?? null),
                'respon_time_status'       => self::slaStatus($sla['response_status'] ?? null),
                'target_resolution_time'   => self::hours($sla['target_resolution_hours'] ?? null),
                'due_date_resolution_time' => self::date($sla['resolution_due_at'] ?? null, 'd M Y H:i'),
                'resolution_time'          => self::hours($sla['resolution_time_hours'] ?? null),
                'resolution_time_status'   => self::slaStatus($sla['resolution_status'] ?? null),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'Last Update',
            'Tiket',
            'Description',
            'Start Date',
            'Close Date',
            'Day on Close',
            'Customer',
            'End Customer',
            'Ticket Lead',
            'Priority',
            'Scale',
            'Status',
            'Type',
            'Module',
            'Assign Delivery',
            'Customer Mandays',
            'Activity Date',
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
