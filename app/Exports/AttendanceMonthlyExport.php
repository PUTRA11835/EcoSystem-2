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
 * Ekspor matriks presensi bulanan: satu baris per karyawan, satu kolom per
 * tanggal.
 *
 * Isi sel memakai kode singkat, bukan ikon: berkas ini dibaca di Excel, dan
 * lambang grafis tidak dapat disaring maupun dijumlahkan di sana.
 *   V = hadir dan sudah check-out
 *   I = hadir tetapi belum check-out
 *   L = terlambat
 *   -  = tidak ada catatan (BUKAN alpa)
 */
class AttendanceMonthlyExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $rows,
        private array $days,
        private Carbon $month
    ) {
    }

    public function title(): string
    {
        return 'Monthly ' . $this->month->format('Y-m');
    }

    public function collection(): Collection
    {
        return collect($this->rows)->values()->map(function ($row, $index) {
            $basic = $row['employee']->basicData;

            $line = [
                $index + 1,
                $basic?->nick_name ?? '—',
                $row['employee']->eci,
                $basic?->department ?? '',
            ];

            foreach ($this->days as $day => $meta) {
                $record = $row['cells'][$day] ?? null;

                $line[] = match (true) {
                    $record === null                  => '-',
                    $record->late_minutes > 0         => 'L',
                    $record->check_out_at !== null    => 'V',
                    default                           => 'I',
                };
            }

            $line[] = $row['present'];
            $line[] = $row['complete'];

            return $line;
        });
    }

    public function headings(): array
    {
        $headings = ['No', 'Employee', 'Employee Code', 'Department'];

        foreach ($this->days as $day => $meta) {
            // Akhir pekan dan hari libur ditandai di judul kolom, karena Excel
            // tidak membawa pewarnaan bersyarat dari halaman web.
            $headings[] = $day . ($meta['is_workday'] ? '' : '*');
        }

        $headings[] = 'Present';
        $headings[] = 'Complete';

        return $headings;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
