<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Overtime\OvertimeRequest;
use App\Models\Overtime\OvertimeSetting;
use App\Services\Overtime\OvertimeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Pengajuan lembur mandiri (ESS).
 *
 * 🔴 `employee_id` SELALU diambil dari sesi, tidak pernah dari badan request.
 * Menyisipkan employee_id orang lain lewat DevTools tidak berpengaruh apa pun.
 */
class MyOvertimeController extends Controller
{
    public function index(OvertimeService $overtime)
    {
        $employeeId = (int) session('user.id');
        $now        = now();

        return view('hr-general.overtime.my-overtime', [
            'requests' => $overtime->history($employeeId),
            'summary'  => $overtime->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            'settings' => OvertimeSetting::current(),
            'month'    => $now->format('F Y'),
        ]);
    }

    public function create(OvertimeService $overtime)
    {
        $settings = OvertimeSetting::current();

        // Batas tanggal dihitung di sini, bukan di Blade, supaya atribut min/max
        // pada input tanggal selalu sejalan dengan aturan yang ditegakkan
        // service — pengguna tidak menemui penolakan setelah menekan kirim.
        $today = now()->startOfDay();

        return view('hr-general.overtime.submit', [
            'settings' => $settings,
            'minDate'  => $settings->hasBackdateLimit()
                ? $today->copy()->subDays($settings->max_backdate_days)->toDateString()
                : null,
            'maxDate'  => $settings->allow_future_date ? null : $today->toDateString(),
            'steps'    => $overtime->activeSteps(),
        ]);
    }

    public function store(Request $request, OvertimeService $overtime)
    {
        $employeeId = (int) session('user.id');
        $data       = $this->validatePayload($request);

        $result = $overtime->submit($employeeId, $data);

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.my-overtime.index')
            ->with('success', 'Overtime request ' . $result['request']->request_no
                . ' submitted and is waiting for review.');
    }

    public function cancel(OvertimeRequest $overtimeRequest, OvertimeService $overtime)
    {
        $employeeId = (int) session('user.id');

        // Kepemilikan diperiksa di dalam service dari id sesi, bukan dari
        // parameter rute — tanpa itu siapa pun dapat membatalkan pengajuan
        // orang lain hanya dengan menebak id-nya.
        $result = $overtime->cancel($overtimeRequest, $employeeId);

        return $result['allowed']
            ? back()->with('success', 'Overtime request cancelled.')
            : back()->with('error', $result['reason']);
    }

    /**
     * Catatan presensi pada satu tanggal — dipakai form pengajuan untuk
     * menampilkan pembanding sebelum karyawan menekan kirim.
     *
     * Mengembalikan JSON, bukan me-render ulang halaman, karena dipanggil saat
     * pengguna mengganti tanggal.
     */
    public function attendanceHint(Request $request, OvertimeService $overtime)
    {
        $employeeId = (int) session('user.id');

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = Carbon::parse($validated['date'])->startOfDay();

        if ($date->greaterThan(now()->startOfDay())) {
            return response()->json([
                'success' => true,
                'message' => 'Future date — no attendance record yet.',
                'data'    => ['state' => 'future', 'day_type' => $overtime->dayTypeFor($date)],
            ]);
        }

        $record = AttendanceRecord::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $date->toDateString())
            ->first();

        if (!$record) {
            return response()->json([
                'success' => true,
                'message' => 'No attendance record found for that date.',
                'data'    => ['state' => 'missing', 'day_type' => $overtime->dayTypeFor($date)],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Attendance found.',
            'data'    => [
                'state'            => 'found',
                'day_type'         => $overtime->dayTypeFor($date),
                'check_in'         => $record->check_in_at?->format('H:i'),
                'check_out'        => $record->check_out_at?->format('H:i'),
                'overtime_minutes' => (int) $record->overtime_minutes,
                'overtime_label'   => OvertimeRequest::formatMinutes((int) $record->overtime_minutes),
            ],
        ]);
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        $settings = OvertimeSetting::current();
        $min      = $settings->require_reason_min_chars;

        $rules = [
            'overtime_date' => ['required', 'date'],
            'start_time'    => ['required', 'date_format:H:i'],
            'end_time'      => ['required', 'date_format:H:i'],
            'reason'        => ['required', 'string', 'min:' . $min, 'max:1000'],
        ];

        if (!$settings->allow_future_date) {
            $rules['overtime_date'][] = 'before_or_equal:' . now()->toDateString();
        }

        if ($settings->hasBackdateLimit()) {
            $rules['overtime_date'][] = 'after_or_equal:'
                . now()->subDays($settings->max_backdate_days)->toDateString();
        }

        return $request->validate($rules, [
            'overtime_date.before_or_equal' => 'Overtime cannot be submitted for a future date.',
            'overtime_date.after_or_equal'  => "Overtime can only be submitted for the last {$settings->max_backdate_days} days.",
            'reason.min'                    => "Please describe the reason in at least {$min} characters.",
        ]);
    }
}
