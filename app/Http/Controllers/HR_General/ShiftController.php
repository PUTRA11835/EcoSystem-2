<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Attendance\AttendanceSetting;
use App\Models\Attendance\EmployeeShift;
use App\Models\Attendance\Shift;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Pola jam kerja (shift) dan penugasannya ke karyawan.
 *
 * Tanpa shift, kata "terlambat" tidak terdefinisi: AttendanceService tidak
 * punya pembanding untuk jam check-in. Karena itu selalu ada satu shift
 * bertanda `is_default` yang dipakai karyawan tanpa penugasan eksplisit —
 * sehingga 207 karyawan tidak perlu ditugaskan satu per satu agar modul
 * presensi berjalan.
 */
class ShiftController extends Controller
{
    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));
        $status = $request->query('status', 'all');

        $shifts = Shift::query()
            ->when($search !== '', fn ($q) => $q->where('name', 'like', "%{$search}%"))
            ->when($status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($status === 'inactive', fn ($q) => $q->where('is_active', false))
            ->withCount(['assignments as active_employees_count' => fn ($q) => $q->whereNull('end_date')])
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('HR_General.settings.shifts.index', compact('shifts', 'search', 'status'));
    }

    public function create()
    {
        return view('HR_General.settings.shifts.form', [
            'shift'     => new Shift([
                'check_in_time'          => '08:00',
                'check_out_time'         => '17:00',
                'late_tolerance_minutes' => 0,
                'break_minutes'          => 60,
                'work_days'              => '1,2,3,4,5',
                'is_active'              => true,
            ]),
            'isEditing' => false,
        ]);
    }

    public function edit(Shift $shift)
    {
        return view('HR_General.settings.shifts.form', [
            'shift'     => $shift,
            'isEditing' => true,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validatePayload($request);

        $shift = DB::transaction(function () use ($data) {
            $this->clearOtherDefaults($data);

            return Shift::create($data);
        });

        return redirect()
            ->route('general.settings.shifts.index')
            ->with('success', "Shift \"{$shift->name}\" has been added.");
    }

    public function update(Request $request, Shift $shift)
    {
        $data = $this->validatePayload($request, $shift);

        DB::transaction(function () use ($data, $shift) {
            $this->clearOtherDefaults($data, $shift->id);

            $shift->update($data);
        });

        return redirect()
            ->route('general.settings.shifts.index')
            ->with('success', "Shift \"{$shift->name}\" has been updated.");
    }

    /**
     * Menghapus shift yang masih dipakai DITOLAK, bukan dibiarkan cascade.
     *
     * `employee_shifts` memakai onDelete('cascade'), jadi tanpa penjagaan di
     * sini satu klik akan menghapus seluruh riwayat penugasannya diam-diam.
     */
    public function destroy(Shift $shift)
    {
        $activeCount = $shift->assignments()->whereNull('end_date')->count();

        if ($activeCount > 0) {
            return back()->with('error',
                "Shift \"{$shift->name}\" still has {$activeCount} assigned employee(s). "
                . 'Release them first before deleting this shift.');
        }

        if ($shift->is_default) {
            return back()->with('error',
                'The default shift cannot be deleted. Set another shift as default first.');
        }

        $name = $shift->name;
        $shift->delete();

        return redirect()
            ->route('general.settings.shifts.index')
            ->with('success', "Shift \"{$name}\" has been deleted.");
    }

    // ── Penugasan karyawan ──────────────────────────────────────────────────

    public function assign(Request $request, Shift $shift)
    {
        $search = trim((string) $request->query('search', ''));

        $assigned = EmployeeShift::query()
            ->where('shift_id', $shift->id)
            ->whereNull('end_date')
            ->with('employee.basicData')
            ->get()
            ->sortBy(fn ($row) => $row->employee?->basicData?->nick_name ?? '')
            ->values();

        $maxShifts = AttendanceSetting::current()->max_shifts_per_employee;

        // Berapa shift aktif yang sudah dimiliki tiap karyawan. Dipakai untuk
        // menandai siapa yang sudah mencapai kuota, alih-alih menyembunyikannya
        // — HR perlu tahu bahwa orangnya ada tetapi kuotanya penuh.
        $activeCounts = EmployeeShift::query()
            ->whereNull('end_date')
            ->selectRaw('employee_id, COUNT(*) AS total')
            ->groupBy('employee_id')
            ->pluck('total', 'employee_id');

        // Yang sudah ditugaskan ke shift INI tidak perlu ditawarkan lagi.
        $alreadyHere = $assigned->pluck('employee_id')->all();

        $candidates = Employee::query()
            ->where('is_active', true)
            ->whereNotIn('employee_id', $alreadyHere)
            ->with('basicData')
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($inner) use ($search) {
                    $inner->where('eci', 'like', "%{$search}%")
                          ->orWhereHas('basicData', fn ($b) => $b->where('nick_name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('eci')
            ->limit(200)
            ->get();

        return view('HR_General.settings.shifts.assign', compact(
            'shift', 'assigned', 'candidates', 'search', 'maxShifts', 'activeCounts'
        ));
    }

    /**
     * Tugaskan karyawan ke shift ini.
     *
     * Penugasan aktif yang lama DIAKHIRI (end_date diisi), tidak dihapus,
     * supaya presensi lama tetap dapat dijelaskan dengan shift yang berlaku
     * saat itu.
     */
    public function storeAssignment(Request $request, Shift $shift)
    {
        $validated = $request->validate([
            'employee_ids'   => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', 'exists:employee,employee_id'],
        ], [
            'employee_ids.required' => 'Select at least one employee to assign.',
        ]);

        $maxShifts = AttendanceSetting::current()->max_shifts_per_employee;

        $result = DB::transaction(function () use ($validated, $shift, $maxShifts) {
            $today   = now()->toDateString();
            $done    = 0;
            $skipped = [];

            foreach ($validated['employee_ids'] as $employeeId) {
                $existing = EmployeeShift::where('employee_id', $employeeId)
                    ->whereNull('end_date')
                    ->get();

                // Sudah ditugaskan ke shift ini — tidak perlu digandakan.
                if ($existing->contains('shift_id', $shift->id)) {
                    continue;
                }

                if ($existing->count() >= $maxShifts) {
                    // Dengan batas 1, penugasan lama DIAKHIRI lalu diganti —
                    // itulah perilaku "pindah shift" yang diharapkan.
                    if ($maxShifts === 1) {
                        EmployeeShift::whereIn('id', $existing->pluck('id'))
                            ->update(['end_date' => $today, 'updated_at' => now()]);
                    } else {
                        // Dengan batas lebih dari satu, mengakhiri penugasan
                        // lama secara diam-diam justru berbahaya: HR mengira
                        // menambah, padahal menggantikan. Lebih baik dilewati
                        // dan dilaporkan.
                        $skipped[] = $employeeId;
                        continue;
                    }
                }

                EmployeeShift::create([
                    'employee_id' => $employeeId,
                    'shift_id'    => $shift->id,
                    'start_date'  => $today,
                    'assigned_by' => session('user.id'),
                ]);

                $done++;
            }

            return ['done' => $done, 'skipped' => count($skipped)];
        });

        $message = "{$result['done']} employee(s) assigned to \"{$shift->name}\".";

        if ($result['skipped'] > 0) {
            $message .= " {$result['skipped']} skipped — they already have the maximum of {$maxShifts} active shift(s). "
                . 'Release one of their shifts first, or raise the limit in Attendance Settings.';
        }

        return back()->with($result['skipped'] > 0 ? 'warning' : 'success', $message);
    }

    /** Lepas penugasan: isi end_date, jangan hapus barisnya. */
    public function releaseAssignment(Shift $shift, EmployeeShift $assignment)
    {
        if ($assignment->shift_id !== $shift->id) {
            return back()->with('error', 'That assignment does not belong to this shift.');
        }

        $assignment->update(['end_date' => now()->toDateString()]);

        return back()->with('success', 'Assignment released. Past attendance records are unaffected.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Hanya boleh ada satu shift default. Ditegakkan di sini karena MariaDB
     * tidak mendukung partial unique index (UNIQUE hanya pada baris bernilai
     * true).
     */
    private function clearOtherDefaults(array $data, ?int $exceptId = null): void
    {
        if (empty($data['is_default'])) {
            return;
        }

        $affected = Shift::where('is_default', true)
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->update(['is_default' => false]);

        if ($affected > 0) {
            Log::info('Default shift flag moved to another shift.', [
                'cleared_from' => $affected,
                'actor_id'     => session('user.id'),
            ]);
        }
    }

    private function validatePayload(Request $request, ?Shift $existing = null): array
    {
        $validated = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('shifts', 'name')->ignore($existing?->id),
            ],
            'check_in_time'          => ['required', 'date_format:H:i'],
            'check_out_time'         => ['required', 'date_format:H:i'],
            'late_tolerance_minutes' => ['required', 'integer', 'between:0,480'],
            'break_minutes'          => ['required', 'integer', 'between:0,480'],
            'work_days'              => ['required', 'array', 'min:1'],
            'work_days.*'            => ['integer', 'between:1,7'],
            'crosses_midnight'       => ['nullable', 'boolean'],
            'is_default'             => ['nullable', 'boolean'],
            'is_active'              => ['nullable', 'boolean'],
            'notes'                  => ['nullable', 'string', 'max:1000'],
        ], [
            'work_days.required'          => 'Select at least one working day.',
            'late_tolerance_minutes.between' => 'Late tolerance must be between 0 and 480 minutes.',
            'break_minutes.between'       => 'Break duration must be between 0 and 480 minutes.',
        ]);

        $crossesMidnight = $request->boolean('crosses_midnight');

        // Jam keluar lebih awal dari jam masuk hanya masuk akal untuk shift
        // yang memang melewati tengah malam. Tanpa penjagaan ini, durasi kerja
        // akan terhitung negatif dan seluruh rekap jam menjadi salah.
        if (!$crossesMidnight && $validated['check_out_time'] <= $validated['check_in_time']) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'check_out_time' => 'Check-out time must be later than check-in time. '
                    . 'Tick "Crosses midnight" if this shift ends on the following day.',
            ]);
        }

        // Shift default harus tetap aktif; kalau tidak, karyawan tanpa
        // penugasan kehilangan acuan jam kerja tanpa peringatan apa pun.
        $isDefault = $request->boolean('is_default');
        $isActive  = $request->boolean('is_active');

        if ($isDefault && !$isActive) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'is_active' => 'The default shift must stay active. Employees without an explicit assignment rely on it.',
            ]);
        }

        if ($existing && $existing->is_default && !$isActive) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'is_active' => 'This is the default shift and cannot be deactivated. Set another shift as default first.',
            ]);
        }

        $validated['work_days']        = implode(',', $validated['work_days']);
        $validated['crosses_midnight'] = $crossesMidnight;
        $validated['is_default']       = $isDefault;
        $validated['is_active']        = $isActive;

        return $validated;
    }
}
