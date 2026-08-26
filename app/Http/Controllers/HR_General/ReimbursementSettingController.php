<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Reimbursement\ReimbursementApprovalStep;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Pengaturan sub-modul Reimbursement: aturan dokumen + alur persetujuan.
 *
 * Halaman ini adalah KATUP PENGAMAN modul. Setiap kebijakan yang dapat MENOLAK
 * pengajuan — batas mundur, batas jumlah item, batas nominal bermode `block`,
 * penguncian periode — harus dapat dilonggarkan dari sini tanpa menunggu
 * perubahan kode. Pelajaran Keputusan D52: satu setelan yang salah pernah
 * mengunci modul Attendance dan hanya bisa dibuka lewat perubahan kode, karena
 * halaman pengaturannya belum pernah dibuat.
 *
 * Bagian alur persetujuan bekerja SUNGGUHAN, bukan gambaran. Menambah langkah
 * kedua di sini langsung berlaku pada dokumen berikutnya — tanpa migrasi dan
 * tanpa penyesuaian kode. Dokumen yang sedang berjalan tidak terpengaruh, karena
 * langkahnya sudah disalin ke masing-masing dokumen saat dibuat.
 */
class ReimbursementSettingController extends Controller
{
    public function edit(ReimbursementService $reimbursement)
    {
        $steps = ReimbursementApprovalStep::forReimbursement()
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('HR_General.settings.reimbursement', [
            'settings' => ReimbursementSetting::current(),
            'steps'    => $steps,

            // Berapa dokumen berjalan yang akan terkena bila langkah baru
            // ditambahkan. Angkanya disebut SEBELUM tombolnya ditekan.
            'openCount' => $reimbursement->countOpenRequestsBefore(
                (int) $steps->max('order_seq') + 1
            ),
            'roles'    => EmployeeRole::orderBy('name')->get(['id', 'name']),

            // Hanya karyawan aktif, dan hanya kolom yang benar-benar dipakai —
            // 209 baris berisi seluruh kolom membuat halaman berat tanpa manfaat.
            'employees' => Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
                ->where('is_active', 1)
                ->get(['employee_id', 'eci'])
                ->sortBy(fn ($e) => $e->basicData?->nick_name ?? $e->eci)
                ->values(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validatePayload($request);

        // Gerbang yang tidak dapat diungkapkan lewat aturan validasi biasa:
        // menuntut tautan bukti sementara daftar host-nya kosong berarti TIDAK
        // ADA tautan yang pernah lolos — modul terkunci oleh setelannya sendiri.
        // Persis kegagalan yang halaman ini ada untuk mencegahnya (D52).
        if ($data['require_supporting_url'] && $data['supporting_url_allowed_hosts'] === '') {
            return back()->withInput()->with('error',
                'Supporting documents are required, so the allowed host list cannot be empty — '
                . 'no link would ever pass. Add at least one host, or turn the requirement off.');
        }

        $settings = ReimbursementSetting::current();
        $before   = $settings->only(array_keys($data));

        $data['updated_by'] = session('user.id');

        $settings->update($data);

        // Cache-nya per-request. Tanpa ini, pembacaan berikutnya DI REQUEST YANG
        // SAMA — termasuk saat me-render ulang halaman — masih memakai nilai lama.
        ReimbursementSetting::forgetCache();

        // Kebijakan reimbursement menentukan apakah karyawan dapat mengajukan
        // hari itu. Perubahannya dicatat supaya bila besok ada keluhan,
        // penyebabnya dapat ditelusuri ke perubahan ini (pola Keputusan D53).
        Log::info('Reimbursement settings updated.', [
            'actor_id' => session('user.id'),
            'before'   => $before,
            'after'    => $settings->fresh()->only(array_keys($before)),
        ]);

        return back()->with('success', 'Reimbursement settings saved.');
    }

    // ── Alur persetujuan ────────────────────────────────────────────────────

    /**
     * Tambah langkah persetujuan.
     *
     * Bila `apply_to_open` dicentang, langkah ini juga diterapkan ke dokumen
     * yang SEDANG BERJALAN — lihat ReimbursementService::applyStepToOpenRequests()
     * untuk aturan asimetrisnya. Sengaja berupa pilihan, bukan otomatis:
     * perubahan yang menyentuh dokumen berjalan harus terlihat dan disengaja,
     * bukan efek samping yang baru disadari belakangan.
     */
    public function storeStep(Request $request, ReimbursementService $reimbursement)
    {
        $data = $this->validateStep($request);

        $data['module']    = ReimbursementApprovalStep::MODULE_REIMBURSEMENT;
        $data['order_seq'] = (int) ReimbursementApprovalStep::forReimbursement()->max('order_seq') + 1;
        $data['is_active'] = true;

        $step = ReimbursementApprovalStep::create($data);

        if (!$request->boolean('apply_to_open')) {
            return back()->with('success', 'Approval step added. It applies to new documents only.');
        }

        $result = $reimbursement->applyStepToOpenRequests($step, (int) session('user.id'));

        if ($result['applied'] === 0) {
            return back()->with('success', 'Approval step added. No document was in progress, so it applies to new documents only.');
        }

        return back()->with('success', 'Approval step added and applied to ' . $result['applied']
            . ' document(s) still in progress: ' . implode(', ', array_slice($result['request_nos'], 0, 5))
            . (count($result['request_nos']) > 5 ? ' …' : ''));
    }

    public function updateStep(Request $request, ReimbursementApprovalStep $step)
    {
        $data = $this->validateStep($request);

        $data['is_active'] = $request->boolean('is_active');

        // Langkah terakhir yang aktif tidak boleh dimatikan. Tanpa satu pun
        // langkah, dokumen baru akan lahir tanpa jalan keluar — tidak dapat
        // disetujui maupun ditolak siapa pun.
        if (!$data['is_active'] && $this->activeStepCount($step->id) === 0) {
            return back()->with('error',
                'At least one approval step must stay active, otherwise new documents cannot be reviewed by anyone.');
        }

        $step->update($data);

        return back()->with('success', 'Approval step updated.');
    }

    public function destroyStep(ReimbursementApprovalStep $step)
    {
        if ($this->activeStepCount($step->id) === 0) {
            return back()->with('error',
                'This is the only active approval step. Add another one before deleting it.');
        }

        DB::transaction(function () use ($step) {
            $step->delete();

            // Rapatkan urutan supaya tidak ada nomor yang bolong. Dokumen yang
            // sedang berjalan TIDAK terpengaruh — langkahnya sudah disalin ke
            // reimbursement_request_approvals saat dokumen dibuat.
            ReimbursementApprovalStep::forReimbursement()
                ->orderBy('order_seq')
                ->get()
                ->each(fn ($s, $i) => $s->update(['order_seq' => $i + 1]));
        });

        return back()->with('success', 'Approval step deleted.');
    }

    /** Geser satu langkah ke atas atau ke bawah. */
    public function moveStep(Request $request, ReimbursementApprovalStep $step)
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbour = ReimbursementApprovalStep::forReimbursement()
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

            // Nilai sementara di luar jangkauan supaya indeks unik tidak bentrok
            // saat kedua baris ditukar.
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
            'company_name'                  => ['required', 'string', 'max:150'],
            'max_backdate_days'             => ['required', 'integer', 'between:0,3650'],
            'max_items_per_request'         => ['required', 'integer', 'between:0,255'],
            'min_item_amount'               => ['required', 'numeric', 'between:0,999999999999'],
            'max_request_amount'            => ['required', 'numeric', 'between:0,999999999999'],
            'over_limit_policy'             => ['required', Rule::in(ReimbursementSetting::OVER_LIMIT_POLICIES)],
            'require_title_min_chars'       => ['required', 'integer', 'between:0,200'],
            'supporting_url_allowed_hosts'  => ['nullable', 'string', 'max:255'],
            'locked_period_policy'          => ['required', Rule::in(ReimbursementSetting::LOCK_POLICIES)],
            'self_approval_fallback_role_id' => ['nullable', 'integer', 'exists:employee_role,id'],
            'accounting_signer_employee_id' => ['nullable', 'integer', 'exists:employee,employee_id'],
            'cashier_signer_employee_id'    => ['nullable', 'integer', 'exists:employee,employee_id'],
            'approver_signer_employee_id'   => ['nullable', 'integer', 'exists:employee,employee_id'],
        ], [
            'max_backdate_days.between'      => 'The backdate window must be between 0 (unlimited) and 3650 days.',
            'max_items_per_request.between'  => 'The item limit must be between 0 (unlimited) and 255.',
            'max_request_amount.between'     => 'The amount limit must be 0 (unlimited) or a positive number.',
            'require_title_min_chars.between' => 'The title length must be between 0 and 200 characters.',
        ]);

        // Checkbox yang tidak dicentang TIDAK dikirim browser sama sekali, jadi
        // nilainya harus ditetapkan di sini — bukan diambil dari $validated.
        $validated['use_branch_name_in_header']    = $request->boolean('use_branch_name_in_header');
        $validated['allow_future_date']            = $request->boolean('allow_future_date');
        $validated['require_supporting_url']       = $request->boolean('require_supporting_url');
        $validated['require_receipt_no']           = $request->boolean('require_receipt_no');
        $validated['allow_self_approval']          = $request->boolean('allow_self_approval');
        $validated['allow_approver_adjust_amount'] = $request->boolean('allow_approver_adjust_amount');

        $validated['supporting_url_allowed_hosts'] = $this->normaliseHosts(
            $validated['supporting_url_allowed_hosts'] ?? ''
        );

        return $validated;
    }

    /**
     * Rapikan daftar host yang boleh dipakai pada tautan bukti.
     *
     * Orang menempelkan URL utuh ("https://drive.google.com/file/d/..."), bukan
     * nama host. Membiarkannya apa adanya membuat pencocokan host tidak pernah
     * cocok, dan pengguna tidak punya cara menebak apa yang salah — halamannya
     * menerima masukannya tanpa keluhan. Jadi dibersihkan di sini: skema, jalur,
     * `www.`, dan huruf besar dibuang; duplikat dihilangkan.
     */
    private function normaliseHosts(string $raw): string
    {
        return collect(explode(',', $raw))
            ->map(function ($host) {
                $host = strtolower(trim($host));
                $host = preg_replace('#^[a-z][a-z0-9+.-]*://#', '', $host);   // buang skema
                $host = explode('/', $host)[0];                                // buang jalur
                $host = preg_replace('#^www\.#', '', $host);

                return trim($host);
            })
            ->filter()
            ->unique()
            ->implode(',');
    }

    private function validateStep(Request $request): array
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:100'],
            // `direct_manager` sengaja TIDAK ada di daftar ini: nilainya sudah
            // terdaftar di model supaya pengaktifannya nanti tidak memerlukan
            // migrasi, tetapi belum dapat dipilih karena hierarki atasan belum
            // ada di basis data.
            'approver_type'           => ['required', Rule::in(ReimbursementApprovalStep::SELECTABLE_TYPES)],
            'approver_role_id'        => ['nullable', 'integer', 'exists:employee_role,id',
                                          'required_if:approver_type,' . ReimbursementApprovalStep::TYPE_ROLE],
            'approver_employee_ids'   => ['nullable', 'array',
                                          'required_if:approver_type,' . ReimbursementApprovalStep::TYPE_EMPLOYEE],
            'approver_employee_ids.*' => ['integer', 'exists:employee,employee_id'],
        ], [
            'approver_role_id.required_if'      => 'Choose which role approves this step.',
            'approver_employee_ids.required_if' => 'Choose at least one employee for this step.',
        ]);

        // Bersihkan field yang tidak relevan dengan tipe yang dipilih, supaya
        // tidak ada sisa nilai lama yang membingungkan saat tipenya diganti.
        if ($validated['approver_type'] === ReimbursementApprovalStep::TYPE_ROLE) {
            $validated['approver_employee_ids'] = null;
        } else {
            $validated['approver_role_id'] = null;
        }

        return $validated;
    }

    /** Jumlah langkah aktif SELAIN satu id yang sedang diperiksa. */
    private function activeStepCount(int $exceptId): int
    {
        return ReimbursementApprovalStep::forReimbursement()
            ->active()
            ->where('id', '!=', $exceptId)
            ->count();
    }
}
