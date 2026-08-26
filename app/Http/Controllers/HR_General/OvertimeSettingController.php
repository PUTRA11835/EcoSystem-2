<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\EmployeeRole;
use App\Models\Overtime\OvertimeApprovalStep;
use App\Models\Overtime\OvertimeSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Pengaturan sub-modul Overtime: aturan lembur + alur persetujuan.
 *
 * Halaman ini adalah KATUP PENGAMAN modul. Setiap kebijakan yang dapat MENOLAK
 * pengajuan — batas mundur, larangan lintas tengah malam, penguncian periode —
 * harus dapat dilonggarkan dari sini tanpa menunggu perubahan kode. Pelajaran
 * Keputusan D52: satu setelan yang salah pernah mengunci modul Attendance dan
 * hanya bisa dibuka lewat perubahan kode, karena halaman pengaturannya belum
 * pernah dibuat.
 *
 * Bagian alur persetujuan bekerja SUNGGUHAN, bukan gambaran. Menambah langkah
 * kedua di sini langsung berlaku pada pengajuan berikutnya — tanpa migrasi dan
 * tanpa penyesuaian kode. Pengajuan yang sedang berjalan tidak terpengaruh,
 * karena langkahnya sudah disalin ke masing-masing pengajuan saat dibuat.
 */
class OvertimeSettingController extends Controller
{
    public function edit()
    {
        return view('HR_General.settings.overtime', [
            'settings' => OvertimeSetting::current(),
            'steps'    => OvertimeApprovalStep::forOvertime()
                ->with('role')
                ->orderBy('order_seq')
                ->get(),
            'roles'    => EmployeeRole::orderBy('name')->get(['id', 'name']),

            // Hanya karyawan aktif, dan hanya kolom yang benar-benar dipakai —
            // 209 baris berisi seluruh kolom membuat halaman berat tanpa manfaat.
            'employees' => \App\Models\Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
                ->where('is_active', 1)
                ->get(['employee_id', 'eci'])
                ->sortBy(fn ($e) => $e->basicData?->nick_name ?? $e->eci)
                ->values(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validatePayload($request);

        $settings = OvertimeSetting::current();
        $before   = $settings->only(array_keys($data));

        $data['updated_by'] = session('user.id');

        $settings->update($data);
        OvertimeSetting::forgetCache();

        // Kebijakan lembur menentukan apakah karyawan dapat mengajukan hari itu.
        // Perubahannya dicatat supaya bila besok ada keluhan, penyebabnya dapat
        // ditelusuri ke perubahan ini (pola Keputusan D53).
        Log::info('Overtime settings updated.', [
            'actor_id' => session('user.id'),
            'before'   => $before,
            'after'    => $settings->only(array_keys($before)),
        ]);

        return back()->with('success', 'Overtime settings saved.');
    }

    // ── Alur persetujuan ────────────────────────────────────────────────────

    public function storeStep(Request $request)
    {
        $data = $this->validateStep($request);

        $data['module']    = OvertimeApprovalStep::MODULE_OVERTIME;
        $data['order_seq'] = (int) OvertimeApprovalStep::forOvertime()->max('order_seq') + 1;
        $data['is_active'] = true;

        OvertimeApprovalStep::create($data);

        return back()->with('success', 'Approval step added. It applies to new requests from now on.');
    }

    public function updateStep(Request $request, OvertimeApprovalStep $step)
    {
        $data = $this->validateStep($request);

        $data['is_active'] = $request->boolean('is_active');

        // Langkah terakhir yang aktif tidak boleh dimatikan. Tanpa satu pun
        // langkah, pengajuan baru akan lahir tanpa jalan keluar — tidak dapat
        // disetujui maupun ditolak siapa pun.
        if (!$data['is_active'] && $this->activeStepCount($step->id) === 0) {
            return back()->with('error',
                'At least one approval step must stay active, otherwise new requests cannot be reviewed by anyone.');
        }

        $step->update($data);

        return back()->with('success', 'Approval step updated.');
    }

    public function destroyStep(OvertimeApprovalStep $step)
    {
        if ($this->activeStepCount($step->id) === 0) {
            return back()->with('error',
                'This is the only active approval step. Add another one before deleting it.');
        }

        DB::transaction(function () use ($step) {
            $step->delete();

            // Rapatkan urutan supaya tidak ada nomor yang bolong. Pengajuan yang
            // sedang berjalan TIDAK terpengaruh — langkahnya sudah disalin ke
            // overtime_request_approvals saat pengajuan dibuat.
            OvertimeApprovalStep::forOvertime()
                ->orderBy('order_seq')
                ->get()
                ->each(fn ($s, $i) => $s->update(['order_seq' => $i + 1]));
        });

        return back()->with('success', 'Approval step deleted.');
    }

    /** Geser satu langkah ke atas atau ke bawah. */
    public function moveStep(Request $request, OvertimeApprovalStep $step)
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbour = OvertimeApprovalStep::forOvertime()
            ->when($validated['direction'] === 'up',
                fn ($q) => $q->where('order_seq', '<', $step->order_seq)->orderByDesc('order_seq'),
                fn ($q) => $q->where('order_seq', '>', $step->order_seq)->orderBy('order_seq'))
            ->first();

        if (!$neighbour) {
            return back()->with('error', 'That step is already at the end of the list.');
        }

        DB::transaction(function () use ($step, $neighbour) {
            $mine  = $step->order_seq;
            $their = $neighbour->order_seq;

            // Nilai sementara di luar jangkauan supaya indeks unik tidak
            // bentrok saat kedua baris ditukar.
            $step->update(['order_seq' => 0]);
            $neighbour->update(['order_seq' => $mine]);
            $step->update(['order_seq' => $their]);
        });

        return back()->with('success', 'Approval order updated.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'max_backdate_days'          => ['required', 'integer', 'between:0,3650'],
            'min_duration_minutes'       => ['required', 'integer', 'between:0,1440'],
            'max_daily_minutes'          => ['required', 'integer', 'between:0,1440'],
            'max_weekly_minutes'         => ['required', 'integer', 'between:0,10080'],
            'mismatch_tolerance_minutes' => ['required', 'integer', 'between:0,1440'],
            'require_reason_min_chars'   => ['required', 'integer', 'between:0,255'],
            'locked_period_policy'       => ['required', Rule::in(OvertimeSetting::LOCK_POLICIES)],
            'self_approval_fallback_role_id' => ['nullable', 'integer', 'exists:employee_role,id'],
        ], [
            'max_backdate_days.between'    => 'The backdate window must be between 0 (unlimited) and 3650 days.',
            'max_daily_minutes.between'    => 'The daily cap must be between 0 (unlimited) and 1440 minutes.',
            'max_weekly_minutes.between'   => 'The weekly cap must be between 0 (unlimited) and 10080 minutes.',
        ]);

        $validated['allow_future_date']          = $request->boolean('allow_future_date');
        $validated['allow_crosses_midnight']     = $request->boolean('allow_crosses_midnight');
        $validated['allow_self_approval']        = $request->boolean('allow_self_approval');
        $validated['allow_approver_adjust_time'] = $request->boolean('allow_approver_adjust_time');

        return $validated;
    }

    private function validateStep(Request $request): array
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:100'],
            // `direct_manager` sengaja TIDAK ada di daftar ini: nilainya sudah
            // terdaftar di model supaya pengaktifannya nanti tidak memerlukan
            // migrasi, tetapi belum dapat dipilih karena hierarki atasan belum
            // ada di basis data.
            'approver_type'           => ['required', Rule::in(OvertimeApprovalStep::SELECTABLE_TYPES)],
            'approver_role_id'        => ['nullable', 'integer', 'exists:employee_role,id',
                                          'required_if:approver_type,' . OvertimeApprovalStep::TYPE_ROLE],
            'approver_employee_ids'   => ['nullable', 'array',
                                          'required_if:approver_type,' . OvertimeApprovalStep::TYPE_EMPLOYEE],
            'approver_employee_ids.*' => ['integer', 'exists:employee,employee_id'],
        ], [
            'approver_role_id.required_if'      => 'Choose which role approves this step.',
            'approver_employee_ids.required_if' => 'Choose at least one employee for this step.',
        ]);

        // Bersihkan field yang tidak relevan dengan tipe yang dipilih, supaya
        // tidak ada sisa nilai lama yang membingungkan saat tipenya diganti.
        if ($validated['approver_type'] === OvertimeApprovalStep::TYPE_ROLE) {
            $validated['approver_employee_ids'] = null;
        } else {
            $validated['approver_role_id'] = null;
        }

        return $validated;
    }

    /** Jumlah langkah aktif SELAIN satu id yang sedang diperiksa. */
    private function activeStepCount(int $exceptId): int
    {
        return OvertimeApprovalStep::forOvertime()
            ->active()
            ->where('id', '!=', $exceptId)
            ->count();
    }
}
