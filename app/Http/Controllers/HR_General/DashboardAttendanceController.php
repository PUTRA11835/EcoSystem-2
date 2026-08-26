<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceRecord;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;

/**
 * Pemasok data untuk blok Attendance di halaman Dashboard.
 *
 * MENGAPA ADA CONTROLLER TERSENDIRI, BUKAN MENUMPANG DashboardController:
 * `DashboardController` dan `home/home.blade.php` adalah berkas produksi di luar
 * kontrak berkas yang boleh disentuh modul ini. Dengan menaruh seluruh data dan
 * tampilan di dalam modul lalu mengisinya lewat fetch(), `home.blade.php` cukup
 * menerima satu baris `@include` — dan bila suatu saat modul HR dimatikan,
 * dashboard tetap utuh alih-alih ikut jatuh.
 *
 * BENTUK JAWABAN sengaja terbagi dua bagian yang berdiri sendiri:
 *   self  — presensi MILIK PEMANGGIL   (slug general.my-attendance)
 *   admin — ringkasan seluruh karyawan (slug general.attendance)
 * Pemegang keduanya menerima dua-duanya; yang tidak memegang apa pun menerima
 * dua-duanya null dan blok tidak dirender sama sekali.
 *
 * 🔴 Seperti seluruh modul ini, `employee_id` diambil dari sesi dan TIDAK PERNAH
 * dari badan request.
 */
class DashboardAttendanceController extends Controller
{
    /** Berapa hari riwayat yang ditampilkan di kartu dashboard. */
    private const HISTORY_DAYS = 7;

    public function widget(Request $request, AttendanceService $attendance)
    {
        $employeeId = (int) session('user.id');

        return response()->json([
            'success' => true,
            'message' => '',
            'data'    => [
                'self'  => $this->can('general.my-attendance')
                    ? $this->selfPayload($employeeId, $attendance)
                    : null,
                'admin' => $this->can('general.attendance')
                    ? $this->adminPayload()
                    : null,
            ],
        ]);
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Izin dibaca dari data yang sudah dibagikan ShareMenuPermissions.
     *
     * Middleware itu berjalan pada seluruh grup `web` dan sudah menyimpan
     * slug-nya di cache; membaca ulang dari Employee di sini berarti query yang
     * sama dijalankan dua kali per permintaan.
     */
    private function can(string $slug): bool
    {
        return in_array($slug, view()->shared('permSlugs', []), true);
    }

    /** Presensi milik pemanggil sendiri. */
    private function selfPayload(int $employeeId, AttendanceService $attendance): array
    {
        $now    = now();
        $record = $attendance->todayRecord($employeeId);
        $shift  = $attendance->activeShift($employeeId);

        return [
            'shift'   => $shift ? ['name' => $shift->name, 'time_range' => $shift->time_range] : null,
            'record'  => $this->recordPayload($record),
            'summary' => $attendance->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            'history' => $attendance->history($employeeId, self::HISTORY_DAYS)
                ->map(fn (AttendanceRecord $r) => [
                    'date'      => $r->attendance_date->translatedFormat('d F Y'),
                    'day'       => $r->attendance_date->translatedFormat('l'),
                    'check_in'  => $r->check_in_at?->format('H:i'),
                    'check_out' => $r->check_out_at?->format('H:i'),
                    'overtime'  => $r->overtime_minutes > 0 ? $this->duration($r->overtime_minutes) : null,
                    'status'    => $r->day_status,
                    'late'      => (int) $r->late_minutes,
                ])
                ->values(),
        ];
    }

    /**
     * Ringkasan presensi hari ini untuk sisi HR.
     *
     * Absent SENGAJA tidak dihitung dari "karyawan aktif dikurangi yang hadir":
     * selama modul Cuti belum ada, sistem tidak dapat membedakan alpa dari cuti,
     * dan tebakan semacam itu menuduh setiap karyawan yang sedang cuti sebagai
     * alpa. Sama persis dengan alasan di AttendanceRecapController::dailySummary().
     */
    private function adminPayload(): array
    {
        $today = now()->toDateString();

        $rows = AttendanceRecord::query()
            ->whereDate('attendance_date', $today)
            ->selectRaw('COUNT(*) AS recorded')
            ->selectRaw('SUM(CASE WHEN check_in_at IS NOT NULL THEN 1 ELSE 0 END) AS checked_in')
            ->selectRaw('SUM(CASE WHEN check_in_at IS NOT NULL AND check_out_at IS NULL THEN 1 ELSE 0 END) AS still_in')
            ->selectRaw('SUM(CASE WHEN late_minutes > 0 THEN 1 ELSE 0 END) AS late')
            ->first();

        return [
            'recorded'   => (int) ($rows->recorded ?? 0),
            'checked_in' => (int) ($rows->checked_in ?? 0),
            'still_in'   => (int) ($rows->still_in ?? 0),
            'late'       => (int) ($rows->late ?? 0),
            // Hanya ditampilkan kepada peninjau koreksi; nol berarti antrean bersih.
            'pending_corrections' => $this->can('general.attendance.correction')
                ? AttendanceCorrection::pending()->count()
                : null,
        ];
    }

    /** Bentuk catatan hari ini. Sama dengan MyAttendanceController agar kartunya seragam. */
    private function recordPayload(?AttendanceRecord $record): ?array
    {
        if (!$record) {
            return null;
        }

        return [
            'check_in_at'      => $record->check_in_at?->format('H:i'),
            'check_out_at'     => $record->check_out_at?->format('H:i'),
            'late_minutes'     => (int) $record->late_minutes,
            'work_minutes'     => (int) $record->work_minutes,
            'overtime_minutes' => (int) $record->overtime_minutes,
            'day_status'       => $record->day_status,
        ];
    }

    /** Menit → "7 h 30 m". Rekap ini dibaca manusia, bukan mesin. */
    private function duration(int $minutes): string
    {
        if ($minutes <= 0) {
            return '0 m';
        }

        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h > 0 ? "{$h} h " : '') . ($m > 0 ? "{$m} m" : ''));
    }
}
