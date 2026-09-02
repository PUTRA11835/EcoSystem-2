<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\PurchaseRequest\PurchaseRequestApprovalStep;
use App\Models\PurchaseRequest\PurchaseRequestItem;
use App\Models\PurchaseRequest\PurchaseRequestSetting;
use App\Services\PurchaseRequest\PurchaseRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Pengaturan sub-modul Purchase Request: aturan dokumen + alur persetujuan.
 *
 * Halaman ini adalah KATUP PENGAMAN modul. Setiap kebijakan yang dapat MENOLAK
 * pengajuan — batas mundur, batas jumlah item, batas kuantitas, daftar satuan,
 * jenis pembebanan, penguncian periode — harus dapat dilonggarkan dari sini
 * tanpa menunggu perubahan kode. Pelajaran Keputusan D52: satu setelan yang
 * salah pernah mengunci modul Attendance dan hanya bisa dibuka lewat perubahan
 * kode, karena halaman pengaturannya belum pernah dibuat.
 *
 * Bagian alur persetujuan bekerja SUNGGUHAN, bukan gambaran. Menambah langkah
 * kedua di sini langsung berlaku pada dokumen berikutnya — tanpa migrasi dan
 * tanpa penyesuaian kode.
 *
 * ── TIGA HAL YANG BERBEDA DARI ReimbursementSettingController ──────────────
 *
 * 1. NOL field penanda tangan (Keputusan D129). Kolom tanda tangan pada cetakan
 *    diturunkan dari langkah alur, jadi tidak ada yang perlu diatur di sini.
 *    Halamannya menjelaskan itu, bukan diam — kalau diam, orang akan mencari
 *    field yang memang sengaja tidak ada.
 *
 * 2. Kolom "Chosen by requester" pada editor alur (Keputusan D126), beserta
 *    penjagaannya: langkah bertanda itu WAJIB punya kandidat.
 *
 * 3. Daftar satuan dan jenis pembebanan diatur di sini sebagai DATA, bukan
 *    konstanta di kode (Keputusan D127 & D128).
 */
class PurchaseRequestSettingController extends Controller
{
    public function edit(PurchaseRequestService $purchaseRequest)
    {
        $steps = PurchaseRequestApprovalStep::forPurchaseRequest()
            ->with('role')
            ->orderBy('order_seq')
            ->get();

        return view('hr-general.settings.purchase-request', [
            'settings' => PurchaseRequestSetting::current(),
            'steps'    => $steps,

            // Berapa dokumen berjalan yang akan terkena bila langkah baru
            // ditambahkan. Angkanya disebut SEBELUM tombolnya ditekan (D116).
            'openCount' => $purchaseRequest->countOpenRequestsBefore(
                (int) $steps->max('order_seq') + 1
            ),
            'roles'    => EmployeeRole::orderBy('name')->get(['id', 'name']),

            // Hanya karyawan aktif, dan hanya kolom yang benar-benar dipakai —
            // 210 baris berisi seluruh kolom membuat halaman berat tanpa manfaat.
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

        // ── Gerbang yang tidak dapat diungkapkan lewat aturan validasi biasa ──
        //
        // Ketiganya punya bentuk yang sama: setelan yang secara teknis sah tetapi
        // membuat modul TIDAK DAPAT DIPAKAI SAMA SEKALI. Persis kegagalan yang
        // halaman ini ada untuk mencegahnya (D52).

        if ($data['allowed_units'] === '') {
            return back()->withInput()->with('error',
                'The unit list cannot be empty — every item needs a unit, so no request would ever pass. '
                . 'Keep at least one unit.');
        }

        if ($data['allowed_cost_center_types'] === '') {
            return back()->withInput()->with('error',
                'At least one cost center type must stay enabled, otherwise no item could ever be charged '
                . 'to anything.');
        }

        // Satuan bawaan yang tidak ada dalam daftarnya akan membuat setiap baris
        // baru lahir dengan satuan yang langsung ditolak validasi.
        $units = explode(',', $data['allowed_units']);
        if (!in_array($data['default_unit'], $units, true)) {
            $data['default_unit'] = $units[0];
        }

        $settings = PurchaseRequestSetting::current();
        $before   = $settings->only(array_keys($data));

        $data['updated_by'] = session('user.id');

        $settings->update($data);

        // Cache-nya per-request. Tanpa ini, pembacaan berikutnya DI REQUEST YANG
        // SAMA — termasuk saat me-render ulang halaman — masih memakai nilai lama.
        PurchaseRequestSetting::forgetCache();

        // Kebijakan ini menentukan apakah karyawan dapat mengajukan hari itu.
        // Perubahannya dicatat supaya bila besok ada keluhan, penyebabnya dapat
        // ditelusuri ke perubahan ini (pola Keputusan D53).
        Log::info('Purchase request settings updated.', [
            'actor_id' => session('user.id'),
            'before'   => $before,
            'after'    => $settings->fresh()->only(array_keys($before)),
        ]);

        return back()->with('success', 'Purchase request settings saved.');
    }

    // ── Alur persetujuan ────────────────────────────────────────────────────

    /**
     * Tambah langkah persetujuan.
     *
     * Bila `apply_to_open` dicentang, langkah ini juga diterapkan ke dokumen
     * yang SEDANG BERJALAN — lihat PurchaseRequestService::applyStepToOpenRequests()
     * untuk aturan asimetrisnya. Sengaja berupa pilihan, bukan otomatis:
     * perubahan yang menyentuh dokumen berjalan harus terlihat dan disengaja.
     */
    public function storeStep(Request $request, PurchaseRequestService $purchaseRequest)
    {
        $data = $this->validateStep($request);

        if (is_string($data)) {
            return back()->withInput()->with('error', $data);
        }

        $data['module']    = PurchaseRequestApprovalStep::MODULE_PURCHASE_REQUEST;
        $data['order_seq'] = (int) PurchaseRequestApprovalStep::forPurchaseRequest()->max('order_seq') + 1;
        $data['is_active'] = true;

        $step = PurchaseRequestApprovalStep::create($data);

        if (!$request->boolean('apply_to_open')) {
            return back()->with('success', 'Approval step added. It applies to new documents only.');
        }

        $result = $purchaseRequest->applyStepToOpenRequests($step, (int) session('user.id'));

        if ($result['applied'] === 0) {
            return back()->with('success', 'Approval step added. No document was in progress, so it applies to new documents only.');
        }

        return back()->with('success', 'Approval step added and applied to ' . $result['applied']
            . ' document(s) still in progress: ' . implode(', ', array_slice($result['request_nos'], 0, 5))
            . (count($result['request_nos']) > 5 ? ' …' : ''));
    }

    public function updateStep(Request $request, PurchaseRequestApprovalStep $step)
    {
        $data = $this->validateStep($request);

        if (is_string($data)) {
            return back()->withInput()->with('error', $data);
        }

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

    public function destroyStep(PurchaseRequestApprovalStep $step)
    {
        if ($this->activeStepCount($step->id) === 0) {
            return back()->with('error',
                'This is the only active approval step. Add another one before deleting it.');
        }

        DB::transaction(function () use ($step) {
            $step->delete();

            // Rapatkan urutan supaya tidak ada nomor yang bolong. Dokumen yang
            // sedang berjalan TIDAK terpengaruh — langkahnya sudah disalin ke
            // purchase_request_approvals saat dokumen dibuat.
            PurchaseRequestApprovalStep::forPurchaseRequest()
                ->orderBy('order_seq')
                ->get()
                ->each(fn ($s, $i) => $s->update(['order_seq' => $i + 1]));
        });

        return back()->with('success', 'Approval step deleted.');
    }

    /** Geser satu langkah ke atas atau ke bawah. */
    public function moveStep(Request $request, PurchaseRequestApprovalStep $step)
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbour = PurchaseRequestApprovalStep::forPurchaseRequest()
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
            'company_name'             => ['required', 'string', 'max:150'],
            'max_backdate_days'        => ['required', 'integer', 'between:0,3650'],
            'max_items_per_request'    => ['required', 'integer', 'between:0,255'],
            'max_qty_per_item'         => ['required', 'numeric', 'between:0,9999999999'],
            'allowed_units'            => ['required', 'string', 'max:255'],
            'default_unit'             => ['required', 'string', 'max:20'],
            'cost_center_types'        => ['nullable', 'array'],
            'cost_center_types.*'      => [Rule::in(PurchaseRequestItem::COST_CENTER_TYPES)],
            'require_title_min_chars'  => ['required', 'integer', 'between:0,200'],
            'locked_period_policy'     => ['required', Rule::in(PurchaseRequestSetting::LOCK_POLICIES)],
            'self_approval_fallback_role_id' => ['nullable', 'integer', 'exists:employee_role,id'],
        ], [
            'max_backdate_days.between'       => 'The backdate window must be between 0 (unlimited) and 3650 days.',
            'max_items_per_request.between'   => 'The item limit must be between 0 (unlimited) and 255.',
            'max_qty_per_item.between'        => 'The quantity limit must be 0 (unlimited) or a positive number.',
            'require_title_min_chars.between' => 'The summary length must be between 0 and 200 characters.',
        ]);

        // Checkbox yang tidak dicentang TIDAK dikirim browser sama sekali, jadi
        // nilainya harus ditetapkan di sini — bukan diambil dari $validated.
        $validated['use_branch_name_in_header']    = $request->boolean('use_branch_name_in_header');
        $validated['allow_future_date']            = $request->boolean('allow_future_date');
        $validated['require_use_date']             = $request->boolean('require_use_date');
        $validated['require_period']               = $request->boolean('require_period');
        $validated['require_cost_center_per_item'] = $request->boolean('require_cost_center_per_item');
        $validated['allow_self_approval']          = $request->boolean('allow_self_approval');
        $validated['allow_approver_adjust_items']  = $request->boolean('allow_approver_adjust_items');
        $validated['allow_requester_cancel']       = $request->boolean('allow_requester_cancel');

        $validated['allowed_units'] = $this->normaliseUnits($validated['allowed_units']);
        $validated['default_unit']  = strtoupper(trim($validated['default_unit']));

        // Kotak centang jenis pembebanan disimpan sebagai CSV, bukan kolom
        // boolean per jenis: menambah jenis ketiga kelak (mis. departemen) jadi
        // satu baris di daftar, bukan satu migrasi.
        $validated['allowed_cost_center_types'] = collect($validated['cost_center_types'] ?? [])
            ->map(fn ($type) => strtolower(trim($type)))
            ->unique()
            ->implode(',');

        unset($validated['cost_center_types']);

        return $validated;
    }

    /**
     * Rapikan daftar satuan.
     *
     * Orang mengetik "pc, unit , SET" — dengan spasi, huruf kecil, dan pemisah
     * yang tidak rapi. Membiarkannya apa adanya membuat pencocokan `unit`
     * gagal untuk baris yang sebenarnya sah, dan pengguna tidak punya cara
     * menebak apa yang salah karena halamannya menerima masukannya tanpa
     * keluhan. Karena itu dinormalkan di sini: huruf besar, tanpa spasi,
     * duplikat dibuang.
     */
    private function normaliseUnits(string $raw): string
    {
        return collect(explode(',', $raw))
            ->map(fn ($unit) => strtoupper(trim($unit)))
            ->filter()
            ->unique()
            ->implode(',');
    }

    /**
     * Validasi satu langkah persetujuan.
     *
     * Mengembalikan array data, atau STRING berisi pesan galat bila ada gerbang
     * yang tidak dapat diungkapkan sebagai aturan validasi biasa. Dipilih begitu
     * — bukan melempar ValidationException — supaya pesannya muncul sebagai
     * notifikasi halaman yang menjelaskan sebabnya, bukan sebagai galat field
     * yang menempel pada input yang sebenarnya tidak salah.
     *
     * @return array<string, mixed>|string
     */
    private function validateStep(Request $request): array|string
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:100'],
            // `direct_manager` sengaja TIDAK ada di daftar ini: nilainya sudah
            // terdaftar di model supaya pengaktifannya nanti tidak memerlukan
            // migrasi, tetapi belum dapat dipilih karena hierarki atasan belum
            // ada di basis data.
            'approver_type'           => ['required', Rule::in(PurchaseRequestApprovalStep::SELECTABLE_TYPES)],
            'approver_role_id'        => ['nullable', 'integer', 'exists:employee_role,id',
                                          'required_if:approver_type,' . PurchaseRequestApprovalStep::TYPE_ROLE],
            'approver_employee_ids'   => ['nullable', 'array',
                                          'required_if:approver_type,' . PurchaseRequestApprovalStep::TYPE_EMPLOYEE],
            'approver_employee_ids.*' => ['integer', 'exists:employee,employee_id'],
        ], [
            'approver_role_id.required_if'      => 'Choose which role approves this step.',
            'approver_employee_ids.required_if' => 'Choose at least one employee for this step.',
        ]);

        // Bersihkan field yang tidak relevan dengan tipe yang dipilih, supaya
        // tidak ada sisa nilai lama yang membingungkan saat tipenya diganti.
        if ($validated['approver_type'] === PurchaseRequestApprovalStep::TYPE_ROLE) {
            $validated['approver_employee_ids'] = null;
        } else {
            $validated['approver_role_id'] = null;
        }

        $validated['requester_selectable'] = $request->boolean('requester_selectable');

        // 🔴 PENJAGAAN D126 — langkah yang penyetujunya dipilih pemohon WAJIB
        // punya kandidat. Tanpa ini, dokumen baru lahir dengan langkah yang tidak
        // dapat ditindak siapa pun: pemohon tidak punya pilihan, dan tidak ada
        // seorang pun yang lolos `allows()`.
        //
        // Diperiksa lewat objek sementara supaya memakai logika kandidat yang
        // SAMA dengan yang dipakai saat pengajuan — bukan salinannya.
        if ($validated['requester_selectable']) {
            $probe = new PurchaseRequestApprovalStep($validated);

            if ($probe->candidateEmployeeIds() === []) {
                return $validated['approver_type'] === PurchaseRequestApprovalStep::TYPE_ROLE
                    ? 'That role has no active employee, so the requester would have nobody to choose from. '
                      . 'Pick a role that someone actually holds, or list the employees directly.'
                    : 'A step chosen by the requester needs at least one candidate employee.';
            }
        }

        return $validated;
    }

    /** Jumlah langkah aktif SELAIN satu id yang sedang diperiksa. */
    private function activeStepCount(int $exceptId): int
    {
        return PurchaseRequestApprovalStep::forPurchaseRequest()
            ->active()
            ->where('id', '!=', $exceptId)
            ->count();
    }
}
