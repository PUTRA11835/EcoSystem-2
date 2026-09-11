<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceApprovalStep;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Services\CashAdvance\CashAdvanceReportService;
use App\Services\CashAdvance\CashAdvanceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Pengaturan sub-modul Cash Advance & Cash Advance Report — SATU halaman untuk
 * keduanya (jawaban C11), dengan DUA editor alur persetujuan.
 *
 * ── LETAKNYA DI MANAGEMENT, BUKAN DI HR & GENERAL (Keputusan D141) ─────────
 * Slug `management.cash-advance-settings`, URL `/management/cash-advance-settings`.
 * Permintaan pemilik sistem, dan alasannya tepat: orang yang mengatur uang
 * perusahaan belum tentu orang yang mengurus kepegawaian.
 *
 * 🔴 BERKASNYA tetap di `HR_General/` dan view-nya di `hr-general/settings/`.
 * Nama BERKAS bukan identitas fungsional — yang mengikat adalah slug, nama rute,
 * dan URL. Halaman ini seluruhnya tentang Cash Advance, dibaca dan dirawat
 * bersama sisa sub-modulnya; letak MENU-nya adalah keputusan izin, bukan
 * keputusan penataan kode. Prinsip yang sama sudah dipegang sejak Attendance.
 *
 * ── HALAMAN INI ADALAH KATUP PENGAMAN MODUL (Keputusan D52) ────────────────
 * Setiap kebijakan yang dapat MENOLAK pengajuan — batas nominal, batas mundur,
 * daftar mata uang, jenis pembebanan, tenggat CAR, penguncian periode — harus
 * dapat dilonggarkan dari sini tanpa menunggu perubahan kode. Kebalikannya juga
 * berlaku dan akan diaudit di A8: setelan yang tersimpan tetapi tidak pernah
 * dibaca satu baris kode pun adalah kegagalan.
 *
 * ── EMPAT PENJAGAAN YANG TIDAK DAPAT DIUNGKAPKAN LEWAT ATURAN VALIDASI ─────
 *
 * 1. Tiap modul WAJIB menyisakan minimal satu langkah AKTIF. Tanpa itu dokumen
 *    baru lahir tanpa jalan keluar — tidak dapat disetujui maupun ditolak.
 * 2. 🔴 Tiap modul WAJIB menyisakan minimal satu langkah aktif ber-`actor_role
 *    = approver`. Tanpa itu kolom "Approved by" pada cetakan tidak punya sumber,
 *    dan kertas yang dicetak keluar dengan kolom persetujuan kosong.
 * 3. Langkah `requester_selectable` WAJIB punya kandidat. Tanpa itu dokumen baru
 *    lahir menunggu orang yang tidak ada.
 * 4. `direct_manager` DITOLAK dengan pesan yang menyebut alasannya — hierarki
 *    atasan belum ada di basis data (pekerjaan tertunda T.2). Ditolak, bukan
 *    diterima lalu diam-diam tidak pernah menemukan penyetuju.
 */
class CashAdvanceSettingController extends Controller
{
    public function edit(CashAdvanceService $ca, CashAdvanceReportService $car)
    {
        $caSteps  = $this->stepsOf(CashAdvanceApprovalStep::MODULE_CA);
        $carSteps = $this->stepsOf(CashAdvanceApprovalStep::MODULE_CAR);

        return view('hr-general.settings.cash-advance', [
            'settings' => CashAdvanceSetting::current(),
            'caSteps'  => $caSteps,
            'carSteps' => $carSteps,

            // Berapa dokumen berjalan yang akan terkena bila langkah baru
            // ditambahkan. Angkanya disebut SEBELUM tombolnya ditekan (D116) —
            // keputusan yang menyentuh dokumen berjalan tidak boleh diambil
            // tanpa tahu berapa banyak yang tersentuh.
            'caOpenCount'  => $ca->countOpenRequestsBefore((int) $caSteps->max('order_seq') + 1),
            'carOpenCount' => $car->countOpenReportsBefore((int) $carSteps->max('order_seq') + 1),

            'roles' => EmployeeRole::orderBy('name')->get(['id', 'name']),

            // 🔴 Berapa karyawan yang MEMEGANG tiap posisi.
            //
            // Tanpa angka ini, memilih posisi di kolom Reference adalah tebakan:
            // layar tidak memberi tahu apakah posisi itu punya pemegang sama
            // sekali, dan langkah tanpa kandidat melahirkan dokumen yang menunggu
            // orang yang tidak ada. Pemilik sistem melaporkan kebingungan persis
            // di titik ini.
            //
            // Dihitung SATU kueri berkelompok, bukan per posisi — 68 posisi
            // berarti 68 kueri kalau memakai Employee::withRole() satu per satu.
            // Definisinya sengaja sama dengan scope withRole(): lewat tabel
            // pivot employee_role_assignment, tanpa menyaring is_active, supaya
            // angka yang DITAMPILKAN sama persis dengan kandidat yang nanti
            // BENAR-BENAR dipakai CashAdvanceApprovalStep::candidateEmployeeIds().
            // Angka yang berbeda dari kenyataan lebih buruk daripada tidak ada.
            'roleCandidates' => DB::table('employee_role_assignment')
                ->select('role_id', DB::raw('COUNT(DISTINCT employee_id) AS total'))
                ->groupBy('role_id')
                ->pluck('total', 'role_id'),

            // Hanya karyawan aktif, dan hanya kolom yang benar-benar dipakai —
            // 207 baris berisi seluruh kolom membuat halaman berat tanpa manfaat.
            'employees' => Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
                ->where('is_active', 1)
                ->get(['employee_id', 'eci'])
                ->sortBy(fn ($e) => $e->basicData?->nick_name ?? $e->eci)
                ->values(),

            // 🔴 Diberitahukan di layar, bukan didiamkan: tanpa cabang aktif,
            // dropdown pembebanan hampa dan `require_cost_center` akan menolak
            // setiap pengajuan. Setelan yang mengunci modul tanpa penjelasan
            // adalah persis kegagalan D52.
            'branchCount' => DB::table('branches')->where('is_active', 1)->count(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $this->validatePayload($request);

        // ── Gerbang yang tidak dapat diungkapkan lewat aturan validasi biasa ──
        //
        // Ketiganya punya bentuk yang sama: setelan yang secara teknis sah tetapi
        // membuat modul TIDAK DAPAT DIPAKAI SAMA SEKALI.

        if ($data['allowed_currencies'] === '') {
            return back()->withInput()->with('error',
                'The currency list cannot be empty — every cash advance needs a currency, '
                . 'so no request would ever pass. Keep at least one.');
        }

        if ($data['require_cost_center'] && $data['allowed_cost_center_types'] === '') {
            return back()->withInput()->with('error',
                'Cost center is required but no cost center type is enabled. '
                . 'Either enable a type, or turn off "Require cost center".');
        }

        if ($data['min_amount'] > 0 && $data['max_amount'] > 0
            && $data['min_amount'] > $data['max_amount']) {
            return back()->withInput()->with('error',
                'The minimum amount cannot be higher than the maximum — no amount would ever pass.');
        }

        // Mata uang bawaan yang tidak ada dalam daftarnya akan membuat setiap
        // pengajuan lahir dengan mata uang yang langsung ditolak validasi.
        $currencies = explode(',', $data['allowed_currencies']);
        if (! in_array($data['default_currency'], $currencies, true)) {
            $data['default_currency'] = $currencies[0];
        }

        $settings = CashAdvanceSetting::current();
        $before   = $settings->only(array_keys($data));

        $data['updated_by'] = session('user.id');

        $settings->update($data);

        // Cache-nya per-request. Tanpa ini, pembacaan berikutnya DI REQUEST YANG
        // SAMA — termasuk saat me-render ulang halaman — masih memakai nilai lama.
        CashAdvanceSetting::forgetCache();

        // Kebijakan ini menentukan apakah karyawan dapat mengajukan hari itu, dan
        // modul ini mengeluarkan uang perusahaan. Perubahannya dicatat supaya
        // bila besok ada keluhan, penyebabnya dapat ditelusuri (pola D53).
        Log::info('Cash advance settings updated.', [
            'actor_id' => session('user.id'),
            'before'   => $before,
            'after'    => $settings->fresh()->only(array_keys($before)),
        ]);

        return back()->with('success', 'Cash advance settings saved.');
    }

    // ── Alur persetujuan — DUA modul lewat satu set rute ────────────────────

    /**
     * Tambah langkah persetujuan.
     *
     * Bila `apply_to_open` dicentang, langkah ini juga diterapkan ke dokumen yang
     * SEDANG BERJALAN — aturan asimetris D116. Sengaja berupa pilihan, bukan
     * otomatis: perubahan yang menyentuh dokumen berjalan harus terlihat dan
     * disengaja.
     */
    public function storeStep(Request $request, CashAdvanceService $ca, CashAdvanceReportService $car)
    {
        $module = $this->moduleOf($request);
        $data   = $this->validateStep($request);

        if (is_string($data)) {
            return back()->withInput()->with('error', $data);
        }

        $data['module']    = $module;
        $data['order_seq'] = (int) $this->stepsOf($module)->max('order_seq') + 1;
        $data['is_active'] = true;

        $step = CashAdvanceApprovalStep::create($data);

        if (! $request->boolean('apply_to_open')) {
            return back()->with('success', 'Approval step added. It applies to new documents only.');
        }

        $result = $module === CashAdvanceApprovalStep::MODULE_CA
            ? $ca->applyStepToOpenRequests($step, (int) session('user.id'))
            : $car->applyStepToOpenReports($step, (int) session('user.id'));

        if ($result['applied'] === 0) {
            return back()->with('success',
                'Approval step added. No document was in progress, so it applies to new documents only.');
        }

        return back()->with('success', 'Approval step added and applied to ' . $result['applied']
            . ' document(s) still in progress: ' . implode(', ', array_slice($result['request_nos'], 0, 5))
            . (count($result['request_nos']) > 5 ? ' …' : ''));
    }

    public function updateStep(Request $request, CashAdvanceApprovalStep $step)
    {
        $data = $this->validateStep($request);

        if (is_string($data)) {
            return back()->withInput()->with('error', $data);
        }

        $data['is_active'] = $request->boolean('is_active');

        // Modulnya TIDAK boleh berpindah lewat form. Memindahkan langkah dari CA
        // ke CAR di tengah jalan akan membuat order_seq bentrok dan meninggalkan
        // salah satu alur tanpa langkah — dan tidak ada alasan sah untuk itu.
        unset($data['module']);

        $guard = $this->guardStepRemoval($step, $data['is_active'], $data['actor_role']);
        if ($guard !== null) {
            return back()->with('error', $guard);
        }

        $step->update($data);

        return back()->with('success', 'Approval step updated.');
    }

    public function destroyStep(CashAdvanceApprovalStep $step)
    {
        $guard = $this->guardStepRemoval($step, false, null);
        if ($guard !== null) {
            return back()->with('error', $guard);
        }

        $module = $step->module;

        DB::transaction(function () use ($step, $module) {
            $step->delete();

            // Rapatkan urutan supaya tidak ada nomor yang bolong. Dokumen yang
            // sedang berjalan TIDAK terpengaruh — langkahnya sudah disalin ke
            // tabel riwayat saat dokumen dibuat.
            //
            // 🔴 Dirapatkan lewat nilai sementara di luar jangkauan lebih dulu.
            // Menulis langsung 1,2,3 bisa bentrok dengan indeks unik
            // (module, order_seq) saat baris yang belum bergerak masih memakai
            // nomor tujuan.
            $steps = $this->stepsOf($module)->sortBy('order_seq')->values();

            foreach ($steps as $i => $s) {
                $s->update(['order_seq' => 100 + $i]);
            }

            foreach ($steps as $i => $s) {
                $s->update(['order_seq' => $i + 1]);
            }
        });

        return back()->with('success', 'Approval step deleted.');
    }

    /** Geser satu langkah ke atas atau ke bawah — tombol panah pada editor. */
    public function moveStep(Request $request, CashAdvanceApprovalStep $step)
    {
        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $neighbour = CashAdvanceApprovalStep::query()
            ->forModule($step->module)
            ->when($validated['direction'] === 'up',
                fn ($q) => $q->where('order_seq', '<', $step->order_seq)->orderByDesc('order_seq'),
                fn ($q) => $q->where('order_seq', '>', $step->order_seq)->orderBy('order_seq'))
            ->first();

        if (! $neighbour) {
            return back()->with('error', 'That step is already at the end of the list.');
        }

        DB::transaction(function () use ($step, $neighbour) {
            $mine  = $step->order_seq;
            $their = $neighbour->order_seq;

            // Nilai sementara di luar jangkauan supaya indeks unik
            // (module, order_seq) tidak bentrok saat kedua baris ditukar.
            $step->update(['order_seq' => 0]);
            $neighbour->update(['order_seq' => $mine]);
            $step->update(['order_seq' => $their]);
        });

        return back()->with('success', 'Approval order updated.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int, CashAdvanceApprovalStep> */
    private function stepsOf(string $module)
    {
        return CashAdvanceApprovalStep::query()
            ->forModule($module)
            ->with('role')
            ->orderBy('order_seq')
            ->get();
    }

    /**
     * Modul yang sedang diedit.
     *
     * 🔴 Divalidasi terhadap daftar TERTUTUP. Satu tabel melayani dua alur
     * (D136); nilai modul di luar daftar akan menciptakan alur ketiga yang tidak
     * pernah dibaca siapa pun — langkah yang tersimpan tetapi tidak pernah
     * berlaku.
     */
    private function moduleOf(Request $request): string
    {
        $module = (string) $request->input('module', CashAdvanceApprovalStep::MODULE_CA);

        return in_array($module, CashAdvanceApprovalStep::MODULES, true)
            ? $module
            : CashAdvanceApprovalStep::MODULE_CA;
    }

    /**
     * Menjaga agar sebuah modul tidak kehilangan langkah yang membuatnya bisa
     * dipakai. Mengembalikan pesan galat, atau null bila aman.
     *
     * @param  bool         $stillActive  Keadaan langkah ini SESUDAH perubahan
     * @param  string|null  $newActorRole Peran langkah ini SESUDAH perubahan
     */
    private function guardStepRemoval(CashAdvanceApprovalStep $step, bool $stillActive, ?string $newActorRole): ?string
    {
        $siblings = $this->stepsOf($step->module)->where('id', '!=', $step->id);
        $active   = $siblings->where('is_active', true);

        // Penjagaan 1 — minimal satu langkah aktif.
        if (! $stillActive && $active->isEmpty()) {
            return 'At least one approval step must stay active for '
                . $this->moduleLabel($step->module)
                . ', otherwise new documents cannot be reviewed by anyone.';
        }

        // Penjagaan 2 — minimal satu langkah aktif ber-actor_role = approver.
        //
        // 🔴 Inilah yang menjaga kolom "Approved by" pada cetakan tetap punya
        // sumber. Tanpa penjagaan ini, seseorang dapat mengubah satu-satunya
        // langkah Approver menjadi Verificator, dan akibatnya baru terlihat di
        // KERTAS — dengan kolom persetujuan kosong.
        $approverAfter = $active->where('actor_role', CashAdvanceApprovalStep::ACTOR_APPROVER)->count()
            + (($stillActive && $newActorRole === CashAdvanceApprovalStep::ACTOR_APPROVER) ? 1 : 0);

        if ($approverAfter === 0) {
            return 'At least one active step for ' . $this->moduleLabel($step->module)
                . ' must have the actor "Approver" — the "Approved by" column on the printed '
                . 'document takes its name from that step.';
        }

        return null;
    }

    private function moduleLabel(string $module): string
    {
        return $module === CashAdvanceApprovalStep::MODULE_CA
            ? 'Cash Advance'
            : 'Cash Advance Report';
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request): array
    {
        $validated = $request->validate([
            'company_name'                  => ['required', 'string', 'max:150'],
            'use_branch_name_in_header'     => ['nullable', 'boolean'],

            'allow_future_date'             => ['nullable', 'boolean'],
            'max_backdate_days'             => ['required', 'integer', 'min:0', 'max:3650'],
            'allow_date_range'              => ['nullable', 'boolean'],

            'min_amount'                    => ['required', 'numeric', 'min:0'],
            'max_amount'                    => ['required', 'numeric', 'min:0'],
            'over_limit_policy'             => ['required', Rule::in(CashAdvanceSetting::LIMIT_POLICIES)],

            'allowed_currencies'            => ['nullable', 'string', 'max:100'],
            'default_currency'              => ['required', 'string', 'max:3'],

            'require_detail_url'            => ['nullable', 'boolean'],
            'detail_url_allowed_hosts'      => ['nullable', 'string', 'max:255'],

            'require_description_min_chars' => ['required', 'integer', 'min:0', 'max:255'],

            'require_cost_center'           => ['nullable', 'boolean'],
            'allowed_cost_center_types'     => ['nullable', 'array'],
            'allowed_cost_center_types.*'   => [Rule::in(CashAdvance::COST_CENTER_TYPES)],

            'allow_self_approval'           => ['nullable', 'boolean'],
            'self_approval_fallback_role_id'=> ['nullable', 'integer', 'exists:employee_role,id'],
            'allow_approver_adjust_amount'  => ['nullable', 'boolean'],
            'allow_requester_cancel'        => ['nullable', 'boolean'],

            'locked_period_policy'          => ['required', Rule::in(CashAdvanceSetting::LOCK_POLICIES)],

            'accounting_signer_employee_id' => ['nullable', 'integer', 'exists:employee,employee_id'],
            'cashier_signer_employee_id'    => ['nullable', 'integer', 'exists:employee,employee_id'],

            'require_car_before_new_ca'     => ['nullable', 'boolean'],
            'car_due_days'                  => ['required', 'integer', 'min:0', 'max:3650'],
            'car_allow_over_amount'         => ['nullable', 'boolean'],
            'car_require_receipt_url'       => ['nullable', 'boolean'],
            'car_multiple_per_ca'           => ['nullable', 'boolean'],
        ]);

        // Kotak centang yang tidak dicentang TIDAK terkirim sama sekali. Tanpa
        // penormalan ini, mematikan sebuah sakelar tidak akan pernah tersimpan —
        // cacat yang diam, karena halamannya tetap tampak menyimpan dengan benar.
        foreach ([
            'use_branch_name_in_header', 'allow_future_date', 'allow_date_range',
            'require_detail_url', 'require_cost_center',
            'allow_self_approval', 'allow_approver_adjust_amount', 'allow_requester_cancel',
            'require_car_before_new_ca', 'car_allow_over_amount',
            'car_require_receipt_url', 'car_multiple_per_ca',
        ] as $flag) {
            $validated[$flag] = $request->boolean($flag);
        }

        $validated['allowed_currencies'] = $this->normaliseCsv(
            (string) ($validated['allowed_currencies'] ?? ''), true
        );
        $validated['default_currency'] = strtoupper(trim((string) $validated['default_currency']));

        $validated['detail_url_allowed_hosts'] = $this->normaliseCsv(
            (string) ($validated['detail_url_allowed_hosts'] ?? ''), false
        );

        // Kotak centang jenis pembebanan datang sebagai array; kolomnya CSV.
        $validated['allowed_cost_center_types'] = implode(',', $validated['allowed_cost_center_types'] ?? []);

        return $validated;
    }

    /** Membersihkan CSV: buang spasi, kosong, dan duplikat. */
    private function normaliseCsv(string $raw, bool $upper): string
    {
        return collect(explode(',', $raw))
            ->map(fn ($v) => $upper ? strtoupper(trim($v)) : strtolower(trim($v)))
            ->filter()
            ->unique()
            ->implode(',');
    }

    /**
     * @return array<string, mixed>|string  Array bila sah, pesan galat bila tidak.
     */
    private function validateStep(Request $request): array|string
    {
        $validated = $request->validate([
            'name'                    => ['required', 'string', 'max:100'],
            'approver_type'           => ['required', Rule::in(CashAdvanceApprovalStep::TYPES)],
            'actor_role'              => ['required', Rule::in(CashAdvanceApprovalStep::ACTOR_ROLES)],
            'approver_role_id'        => ['nullable', 'integer', 'exists:employee_role,id'],
            'approver_employee_ids'   => ['nullable', 'array'],
            'approver_employee_ids.*' => ['integer', 'exists:employee,employee_id'],
            'requester_selectable'    => ['nullable', 'boolean'],
        ]);

        $type = $validated['approver_type'];

        // Penjagaan 4 — direct_manager ditolak dengan alasan yang disebutkan.
        if ($type === CashAdvanceApprovalStep::TYPE_DIRECT_MANAGER) {
            return 'Direct Manager cannot be used yet: this system has no reporting-line data '
                 . '(employee.reports_to_id does not exist and direct_supervision is empty for every employee). '
                 . 'Use "By Position" or "Direct User" instead.';
        }

        if ($type === CashAdvanceApprovalStep::TYPE_ROLE && empty($validated['approver_role_id'])) {
            return 'Choose a position for a "By Position" step.';
        }

        if ($type === CashAdvanceApprovalStep::TYPE_EMPLOYEE && empty($validated['approver_employee_ids'])) {
            return 'Choose at least one employee for a "Direct User" step.';
        }

        // Kolom yang tidak dipakai tipe ini DIPAKSA NULL, bukan dibiarkan berisi
        // nilai lama. Langkah yang menyimpan role DAN daftar karyawan sekaligus
        // membuat pertanyaan "siapa penyetujunya" punya dua jawaban.
        if ($type === CashAdvanceApprovalStep::TYPE_ROLE) {
            $validated['approver_employee_ids'] = null;
        } else {
            $validated['approver_role_id'] = null;
        }

        $validated['requester_selectable'] = $request->boolean('requester_selectable');

        // Penjagaan 3 — langkah selectable WAJIB punya kandidat.
        if ($validated['requester_selectable']) {
            $probe = new CashAdvanceApprovalStep($validated);

            if ($probe->candidateEmployeeIds() === []) {
                return 'A step marked "Chosen by requester" needs at least one candidate. '
                     . 'The position you picked has no active employee assigned to it.';
            }
        }

        return $validated;
    }
}
