<?php

namespace App\Exports;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor rekap presensi harian.
 *
 * Menerima koleksi yang SUDAH difilter controller, bukan memfilter ulang di
 * sini — dengan begitu isi berkas selalu sama persis dengan yang dilihat di
 * layar. Mengekspor seluruh data padahal layar sedang difilter adalah salah
 * satu keluhan paling umum pada fitur semacam ini.
 */
class AttendanceDailyExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private Collection $records,
        private Carbon $date
    ) {
    }

    public function title(): string
    {
        return 'Daily ' . $this->date->format('Y-m-d');
    }

    public function collection(): Collection
    {
        return $this->records->values()->map(function ($record, $index) {
            $basic = $record->employee?->basicData;

            return [
                $index + 1,
                $record->attendance_date->format('Y-m-d'),
                $basic?->nick_name ?? '—',
                $record->employee?->eci ?? '',
                $basic?->department ?? '',
                $basic?->position ?? '',
                $record->shift?->name ?? '',
                $record->check_in_at?->format('H:i') ?? '',
                $record->check_out_at?->format('H:i') ?? '',
                $record->locationSummary('check_in'),
                $record->geofenceVerdict('check_in') ?? '',
                $record->locationSummary('check_out'),
                $record->geofenceVerdict('check_out') ?? '',
                ucfirst($record->day_status),
                $record->late_minutes,
                $record->early_leave_minutes,
                $record->work_minutes,
                $record->overtime_minutes,
                implode(', ', $record->flags ?? []),
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No', 'Date', 'Employee', 'Employee Code', 'Department', 'Position', 'Shift',
            'Check-in', 'Check-out',
            'Check-in Location', 'Check-in Geofence',
            'Check-out Location', 'Check-out Geofence',
            'Attendance', 'Late (min)', 'Early Leave (min)', 'Work (min)', 'Overtime (min)',
            'Flags',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
