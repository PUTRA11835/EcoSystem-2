<?php

namespace App\Exports;

use App\Models\Overtime\OvertimeRequest;
use App\Models\Overtime\OvertimeRequestApproval;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Ekspor pengajuan lembur.
 *
 * Menerima koleksi yang SUDAH difilter controller, bukan memfilter ulang di
 * sini — dengan begitu isi berkas selalu sama persis dengan yang dilihat di
 * layar (Keputusan D48).
 *
 * Durasi ditulis dalam DUA kolom: menit mentah dan label terbaca. Menitnya yang
 * dapat dijumlahkan dan disaring di Excel; labelnya untuk dibaca manusia.
 * Menulis hanya labelnya membuat kolom itu tidak bisa dihitung sama sekali —
 * kesalahan yang sama pernah dihindari pada ekspor rekap bulanan (Keputusan D47).
 *
 * Kolom nominal rupiah SENGAJA BELUM ADA: basis data belum punya sumber upah,
 * sehingga kolom itu hanya akan berisi sel kosong di setiap baris.
 */
class OvertimeExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private Collection $requests)
    {
    }

    public function title(): string
    {
        return 'Overtime';
    }

    public function collection(): Collection
    {
        return $this->requests->values()->map(function (OvertimeRequest $request, $index) {
            $basic = $request->employee?->basicData;

            $decided = $request->approvals
                ->whereIn('status', [
                    OvertimeRequestApproval::STATUS_APPROVED,
                    OvertimeRequestApproval::STATUS_REJECTED,
                ]);

            return [
                $index + 1,
                $request->request_no,
                $basic?->nick_name ?? '—',
                $request->employee?->eci ?? '',
                $basic?->department ?? '',
                $basic?->position ?? '',
                $request->overtime_date->format('Y-m-d'),
                $request->dayTypeLabel(),
                substr((string) $request->start_time, 0, 5),
                substr((string) $request->end_time, 0, 5),
                $request->crosses_midnight ? 'Yes' : 'No',
                $request->duration_minutes,
                $request->durationLabel(),
                $request->attendance_overtime_minutes ?? '',
                ucfirst(str_replace('_', ' ', $request->status)),
                $request->statusLabel(),
                $decided->map(fn ($a) => $a->step_name . ': ' . $a->status
                    . ($a->actor?->basicData?->nick_name ? ' (' . $a->actor->basicData->nick_name . ')' : ''))
                    ->implode(' | '),
                $decided->pluck('notes')->filter()->implode(' | '),
                $request->original_start_time
                    ? substr((string) $request->original_start_time, 0, 5) . '-' . substr((string) $request->original_end_time, 0, 5)
                    : '',
                $request->reason,
                implode(', ', $request->flags ?? []),
                $request->created_at?->format('Y-m-d H:i') ?? '',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No', 'Request No', 'Employee', 'Employee Code', 'Department', 'Position',
            'Date', 'Day Type', 'Start', 'End', 'Overnight',
            'Duration (min)', 'Duration',
            'Attendance Overtime (min)',
            'Status', 'Status Detail',
            'Approval Trail', 'Approver Notes',
            'Originally Claimed',
            'Reason', 'Flags', 'Submitted At',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
