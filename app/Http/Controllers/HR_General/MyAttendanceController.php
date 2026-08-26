<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Attendance\AttendanceSource;
use App\Models\Employee;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Presensi mandiri. Dipakai SELURUH karyawan, termasuk Admin dan HR.
 *
 * 🔴 ATURAN KEAMANAN PALING PENTING DI MODUL INI:
 * `employee_id` selalu diambil dari session('user')['id'], dan TIDAK PERNAH
 * dari badan request. Bila dibaca dari request, siapa pun yang membuka
 * DevTools dapat mencatatkan presensi atas nama rekannya — dan seluruh
 * investasi geofence menjadi tidak berarti.
 */
class MyAttendanceController extends Controller
{
    public function index(Request $request, AttendanceService $attendance)
    {
        $employeeId = $this->currentEmployeeId();

        $employee = Employee::with('basicData')->find($employeeId);
        $now      = now();

        return view('hr-general.attendance.my-attendance', [
            'employee'      => $employee,
            'shift'         => $attendance->activeShift($employeeId),
            'record'        => $attendance->todayRecord($employeeId),
            'summary'       => $attendance->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            'history'       => $attendance->history($employeeId, 30),
            'settings'      => AttendanceSetting::current(),
            'sources'       => AttendanceSource::ordered()->get(),
            'activeProject' => $this->activeProjectName($employeeId),
            'today'         => $now,
            'corrections'   => AttendanceCorrection::where('employee_id', $employeeId)
                ->orderByDesc('created_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function checkIn(Request $request, AttendanceService $attendance)
    {
        return $this->punch($request, $attendance, 'checkIn');
    }

    public function checkOut(Request $request, AttendanceService $attendance)
    {
        return $this->punch($request, $attendance, 'checkOut');
    }

    /** Status hari ini, untuk menyegarkan kartu tanpa memuat ulang halaman. */
    public function today(Request $request, AttendanceService $attendance)
    {
        $employeeId = $this->currentEmployeeId();
        $record     = $attendance->todayRecord($employeeId);
        $now        = now();

        return response()->json([
            'success' => true,
            'message' => '',
            'data'    => [
                'record'  => $this->recordPayload($record),
                'summary' => $attendance->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            ],
        ]);
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function punch(Request $request, AttendanceService $attendance, string $action)
    {
        $data = $this->validatePayload($request);

        // Metadata sisi server. Sengaja TIDAK diambil dari request supaya
        // tidak dapat dipalsukan dari browser.
        $data['ip']         = $request->ip();
        $data['user_agent'] = $request->userAgent();

        $result = $attendance->{$action}($this->currentEmployeeId(), $data);

        if (!$result['allowed']) {
            return response()->json([
                'success' => false,
                'message' => $result['reason'],
            ], 422);
        }

        return response()->json([
            'success' => true,
            'message' => $result['reason'],
            'data'    => ['record' => $this->recordPayload($result['record'])],
        ]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'accuracy'    => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'connection'  => ['nullable', 'string', 'max:20'],
            'gps_status'  => ['nullable', 'string', 'max:30'],
            'client_time' => ['nullable', 'string', 'max:40'],
        ]);
    }

    /**
     * Identitas karyawan aktif.
     *
     * Aplikasi ini memakai session kustom, bukan Auth::user(). Middleware
     * CheckAuthToken sudah memastikan sesi ada sebelum sampai ke sini.
     */
    private function currentEmployeeId(): int
    {
        return (int) session('user.id');
    }

    /** Nama proyek yang sedang dijalani, untuk kartu identitas. */
    private function activeProjectName(int $employeeId): ?string
    {
        $today = now()->toDateString();

        return DB::table('delivery_project_employee as dpe')
            ->join('delivery_projects as dp', 'dp.id', '=', 'dpe.delivery_projects_id')
            ->where('dpe.employee_id', $employeeId)
            ->where(fn ($q) => $q->whereNull('dpe.start_date')->orWhere('dpe.start_date', '<=', $today))
            ->where(fn ($q) => $q->whereNull('dpe.end_date')->orWhere('dpe.end_date', '>=', $today))
            ->orderByDesc('dpe.start_date')
            ->value('dp.name');
    }

    /** Bentuk JSON catatan hari ini yang dipakai halaman untuk menyegarkan diri. */
    private function recordPayload($record): ?array
    {
        if (!$record) {
            return null;
        }

        return [
            'check_in_at'        => $record->check_in_at?->format('H:i'),
            'check_out_at'       => $record->check_out_at?->format('H:i'),
            'late_minutes'       => $record->late_minutes,
            'work_minutes'       => $record->work_minutes,
            'overtime_minutes'   => $record->overtime_minutes,
            'day_status'         => $record->day_status,
            'check_in_verdict'   => $record->geofenceVerdict('check_in'),
            'check_out_verdict'  => $record->geofenceVerdict('check_out'),
            'check_in_summary'   => $record->locationSummary('check_in'),
            'check_out_summary'  => $record->locationSummary('check_out'),
        ];
    }
}
