<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceCorrection;
use App\Models\Attendance\AttendanceRecord;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Attendance\Shift;
use App\Models\Notification;
use App\Services\Attendance\AttendanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pengajuan dan persetujuan koreksi jam presensi.
 *
 * Check-in dan check-out hanya boleh sekali sehari. Bila jamnya keliru —
 * karyawan lupa absen, absen terlalu cepat, atau lokasinya tidak terbaca —
 * jalur perbaikannya adalah alur ini, BUKAN mengizinkan presensi berulang.
 * Dengan begitu setiap perubahan jam selalu meninggalkan jejak siapa yang
 * meminta, apa alasannya, dan siapa yang menyetujui.
 */
class AttendanceCorrectionController extends Controller
{
    // ── Sisi karyawan ───────────────────────────────────────────────────────

    public function store(Request $request)
    {
        $employeeId = (int) session('user.id');
        $settings   = AttendanceSetting::current();

        if (!$settings->allow_self_correction) {
            return back()->with('error',
                'Attendance corrections are currently disabled. Please contact HR directly.');
        }

        $data = $this->validateSubmission($request, $settings);

        // Satu tanggal hanya boleh punya satu pengajuan yang belum diproses.
        // Tanpa aturan ini, HR menerima antrean berisi beberapa versi
        // permintaan untuk hari yang sama dan tidak tahu mana yang berlaku.
        $duplicate = AttendanceCorrection::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $data['attendance_date'])
            ->where('status', AttendanceCorrection::STATUS_PENDING)
            ->exists();

        if ($duplicate) {
            return back()->with('error',
                'You already have a pending correction for that date. '
                . 'Wait for it to be reviewed before submitting another one.');
        }

        $record = AttendanceRecord::where('employee_id', $employeeId)
            ->whereDate('attendance_date', $data['attendance_date'])
            ->first();

        AttendanceCorrection::create([
            'attendance_record_id' => $record?->id,
            'employee_id'          => $employeeId,
            'attendance_date'      => $data['attendance_date'],
            'requested_check_in'   => $data['requested_check_in'] ?? null,
            'requested_check_out'  => $data['requested_check_out'] ?? null,
            'reason'               => $data['reason'],
            'status'               => AttendanceCorrection::STATUS_PENDING,
        ]);

        return back()->with('success',
            'Your attendance correction has been submitted and is waiting for HR review.');
    }

    /** Batalkan pengajuan sendiri selama belum diproses. */
    public function cancel(AttendanceCorrection $correction)
    {
        $employeeId = (int) session('user.id');

        // Pemilik diperiksa dari sesi, bukan dari request — tanpa ini siapa pun
        // dapat membatalkan pengajuan orang lain hanya dengan menebak id-nya.
        if ($correction->employee_id !== $employeeId) {
            abort(403);
        }

        if (!$correction->isPending()) {
            return back()->with('error', 'That correction has already been reviewed.');
        }

        $correction->update(['status' => AttendanceCorrection::STATUS_CANCELLED]);

        return back()->with('success', 'Correction request cancelled.');
    }

    // ── Sisi HR ─────────────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', AttendanceCorrection::STATUS_PENDING);

        $corrections = AttendanceCorrection::query()
            ->with(['employee.basicData', 'record', 'approver.basicData'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('reason', 'like', "%{$search}%")
                          ->orWhereHas('employee', fn ($e) => $e->where('eci', 'like', "%{$search}%"))
                          ->orWhereHas('employee.basicData', fn ($b) => $b->where('nick_name', 'like', "%{$search}%"));
                });
            })
            ->orderByRaw("FIELD(status, 'pending') DESC")
            ->orderByDesc('attendance_date')
            ->paginate(25)
            ->withQueryString();

        $counts = AttendanceCorrection::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('hr-general.attendance.corrections', [
            'corrections' => $corrections,
            'search'      => $search,
            'status'      => $status,
            'counts'      => [
                'pending'  => (int) ($counts[AttendanceCorrection::STATUS_PENDING] ?? 0),
                'approved' => (int) ($counts[AttendanceCorrection::STATUS_APPROVED] ?? 0),
                'rejected' => (int) ($counts[AttendanceCorrection::STATUS_REJECTED] ?? 0),
            ],
        ]);
    }

    /**
     * Setujui koreksi: perbarui jam presensi, simpan nilai lama, hitung ulang
     * keterlambatan.
     */
    public function approve(Request $request, AttendanceCorrection $correction, AttendanceService $attendance)
    {
        if (!$correction->isPending()) {
            return back()->with('error', 'That correction has already been reviewed.');
        }

        $validated = $request->validate([
            'hr_note' => ['nullable', 'string', 'max:255'],
        ]);

        DB::transaction(function () use ($correction, $validated, $attendance) {
            $record = $correction->attendance_record_id
                ? AttendanceRecord::find($correction->attendance_record_id)
                : AttendanceRecord::where('employee_id', $correction->employee_id)
                    ->whereDate('attendance_date', $correction->attendance_date)
                    ->first();

            $date  = Carbon::parse($correction->attendance_date);
            $shift = $attendance->activeShift($correction->employee_id);

            // Koreksi untuk tanggal yang belum punya catatan sama sekali —
            // karyawan lupa absen — barisnya dibuatkan di sini.
            if (!$record) {
                $record = new AttendanceRecord([
                    'employee_id'     => $correction->employee_id,
                    'attendance_date' => $date->toDateString(),
                    'period_year'     => (int) $date->format('Y'),
                    'period_month'    => (int) $date->format('n'),
                    'shift_id'        => $shift?->id,
                ]);
            }

            // Nilai lama disimpan SAAT PERSETUJUAN, bukan saat pengajuan,
            // supaya jejaknya akurat meski jamnya sempat berubah di antaranya.
            $correction->original_check_in  = $record->check_in_at?->format('H:i:s');
            $correction->original_check_out = $record->check_out_at?->format('H:i:s');

            // 🔴 Hanya sisi yang BENAR-BENAR diminta yang ditandai sebagai hasil
            // koreksi. Sebelumnya penandaannya di tingkat baris, sehingga
            // mengoreksi jam masuk saja membuat jam keluar ikut berlabel
            // "Correction" padahal tidak pernah disentuh — riwayatnya jadi
            // menyesatkan bagi karyawan maupun HR.
            if ($correction->requested_check_in) {
                $record->check_in_at     = $date->copy()->setTimeFromTimeString($correction->requested_check_in);
                $record->check_in_source = AttendanceRecord::SOURCE_CORRECTION;
            }

            if ($correction->requested_check_out) {
                $record->check_out_at     = $date->copy()->setTimeFromTimeString($correction->requested_check_out);
                $record->check_out_source = AttendanceRecord::SOURCE_CORRECTION;
            }

            $this->recalculate($record, $shift);

            // Ringkasan tingkat baris tetap menandai bahwa baris ini pernah
            // dikoreksi — dipakai rekap harian dan ekspor.
            $record->source = AttendanceRecord::SOURCE_CORRECTION;
            $record->flags  = collect($record->flags ?? [])
                ->push(AttendanceRecord::FLAG_CORRECTED)
                ->unique()->values()->all();

            $record->save();

            $correction->fill([
                'attendance_record_id' => $record->id,
                'status'               => AttendanceCorrection::STATUS_APPROVED,
                'approved_by'          => session('user.id'),
                'approved_by_type'     => AttendanceCorrection::APPROVER_HR,
                'approved_at'          => now(),
                'hr_note'              => $validated['hr_note'] ?? null,
            ])->save();
        });

        $this->notify($correction, 'approved');

        return back()->with('success', 'Correction approved and attendance updated.');
    }

    public function reject(Request $request, AttendanceCorrection $correction)
    {
        if (!$correction->isPending()) {
            return back()->with('error', 'That correction has already been reviewed.');
        }

        // Catatan WAJIB saat menolak. Penolakan tanpa alasan hanya memindahkan
        // pertanyaan karyawan ke jalur lain — biasanya ke meja HR langsung.
        $validated = $request->validate([
            'hr_note' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'hr_note.required' => 'Please explain why the correction is rejected.',
        ]);

        $correction->update([
            'status'           => AttendanceCorrection::STATUS_REJECTED,
            'approved_by'      => session('user.id'),
            'approved_by_type' => AttendanceCorrection::APPROVER_HR,
            'approved_at'      => now(),
            'hr_note'          => $validated['hr_note'],
        ]);

        $this->notify($correction, 'rejected');

        return back()->with('success', 'Correction rejected.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function validateSubmission(Request $request, AttendanceSetting $settings): array
    {
        $earliest = now()->subDays($settings->correction_max_days)->toDateString();

        return $request->validate([
            'attendance_date'     => ['required', 'date', 'after_or_equal:' . $earliest, 'before_or_equal:' . now()->toDateString()],
            'requested_check_in'  => ['nullable', 'date_format:H:i', 'required_without:requested_check_out'],
            'requested_check_out' => ['nullable', 'date_format:H:i'],
            'reason'              => ['required', 'string', 'min:10', 'max:1000'],
        ], [
            'attendance_date.after_or_equal'  => "Corrections can only be submitted for the last {$settings->correction_max_days} days.",
            'attendance_date.before_or_equal' => 'Corrections cannot be submitted for a future date.',
            'requested_check_in.required_without' => 'Enter a new check-in time, a new check-out time, or both.',
            'reason.min'                      => 'Please describe the reason in at least 10 characters.',
        ]);
    }

    /** Hitung ulang turunan setelah jamnya berubah. */
    private function recalculate(AttendanceRecord $record, ?Shift $shift): void
    {
        $in  = $record->check_in_at;
        $out = $record->check_out_at;

        if ($shift && $in) {
            $scheduled = $in->copy()->setTimeFromTimeString((string) $shift->check_in_time)
                ->addMinutes($shift->late_tolerance_minutes);

            $record->late_minutes = $in->greaterThan($scheduled)
                ? (int) floor($scheduled->diffInMinutes($in))
                : 0;
        } else {
            $record->late_minutes = 0;
        }

        if ($in && $out && $out->greaterThan($in)) {
            $gross = (int) floor($in->diffInMinutes($out));
            $record->work_minutes = max(0, $gross - ($shift?->break_minutes ?? 0));
        } else {
            $record->work_minutes = 0;
        }

        $record->day_status = $record->late_minutes > 0
            ? AttendanceRecord::STATUS_LATE
            : AttendanceRecord::STATUS_PRESENT;
    }

    /**
     * Beri tahu karyawan hasil peninjauan.
     *
     * Dibungkus try/catch karena kegagalan mengirim notifikasi tidak boleh
     * membatalkan koreksi yang sudah tersimpan — tetapi tetap dicatat ke log
     * supaya kegagalannya tidak hilang diam-diam.
     */
    private function notify(AttendanceCorrection $correction, string $outcome): void
    {
        try {
            $date = Carbon::parse($correction->attendance_date)->format('d M Y');

            Notification::create([
                'employee_id'      => $correction->employee_id,
                'type'             => 'attendance_correction_' . $outcome,
                'from_employee_id' => session('user.id'),
                'preview'          => "Your attendance correction for {$date} was {$outcome}."
                    . ($correction->hr_note ? ' Note: ' . $correction->hr_note : ''),
                'link'             => '/general/my-attendance',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send attendance correction notification.', [
                'correction_id' => $correction->id,
                'outcome'       => $outcome,
                'message'       => $e->getMessage(),
            ]);
        }
    }
}
