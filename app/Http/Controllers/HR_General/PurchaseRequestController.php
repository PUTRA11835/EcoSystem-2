<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PurchaseRequest\PurchaseRequest;
use App\Models\PurchaseRequest\PurchaseRequestSetting;
use App\Services\PurchaseRequest\PurchaseRequestService;
use Illuminate\Http\Request;

/**
 * Pengelolaan purchase request (sisi HR / GA / penyetuju).
 *
 * Pemisahan izin yang sengaja tidak digabung, meniru Overtime & Reimbursement
 * (Keputusan D77):
 *   general.purchase-request          boleh MEMBUKA halaman dan membaca dokumen
 *   general.purchase-request.approve  boleh bertindak pada langkah yang menunggu DIRINYA
 *   general.purchase-request.manage   boleh MENGUBAH dan MENGHAPUS dokumen
 *   general.purchase-request.export   boleh mengunduh Excel
 *
 * Tanpa pemisahan itu, memberi hak meninjau otomatis memberi hak menghapus
 * dokumen yang menjadi dasar pengadaan.
 *
 * 🔴 DUA LAPIS IZIN, JANGAN DISATUKAN. Slug menjawab "boleh membuka halaman
 * ini?"; langkah persetujuan menjawab "dokumen ini menunggu siapa?". Tombol
 * Approve/Reject baru dirender bila KEDUANYA terpenuhi — slug lewat `can()`,
 * langkah lewat `PurchaseRequestService::canAct()`.
 *
 * DUA JALAN MENUJU EDIT — jangan disederhanakan jadi satu (lihat
 * canEditDocument()): pemegang `.manage` kapan pun selama dokumen terbuka, dan
 * penyetuju yang sedang mendapat giliran bila `allow_approver_adjust_items`
 * dinyalakan. Karena itu rute `edit`/`update` dijaga slug HALAMAN, bukan
 * `.manage`, dan keputusannya diperiksa DUA KALI — saat merender tombol dan saat
 * menyimpan. `destroy` TETAP `.manage`.
 */
class PurchaseRequestController extends Controller
{
    public function index(Request $request, PurchaseRequestService $service)
    {
        $filters = $this->filters($request);
        $actorId = (int) session('user.id');

        // Id yang menjadi giliran orang ini. Dihitung sekali lalu dipakai untuk
        // menandai baris DAN untuk menyaring — supaya penyetuju tidak perlu
        // menebak mana yang menunggu dirinya.
        $mineIds = $service->pendingIdsFor($actorId);

        $requests = $this->baseQuery($filters)
            ->when($filters['scope'] === 'mine', fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->orderByRaw("FIELD(status, 'submitted', 'in_review') DESC")
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Hak mengubah dihitung PER DOKUMEN, bukan sekali untuk seluruh halaman:
        // penyetuju yang diizinkan menyesuaikan item hanya boleh menyentuh
        // dokumen yang sedang menunggu dirinya (lihat canEditDocument()).
        $editableIds = $requests->getCollection()
            ->filter(fn ($r) => $this->canEditDocument($r, $service))
            ->pluck('id')
            ->all();

        // Hanya jumlah dokumen per status — dokumen ini memang tidak punya
        // nominal untuk dijumlahkan, dan menjumlahkan kuantitas lintas dokumen
        // tidak berarti apa-apa ("20 PC + 5 SET" bukan satu angka).
        $counts = PurchaseRequest::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        return view('hr-general.purchase-request.index', [
            'requests'    => $requests,
            'filters'     => $filters,
            'mineIds'     => $mineIds,
            'editableIds' => $editableIds,
            'settings'    => PurchaseRequestSetting::current(),
            'canManage'   => $this->canManage(),
            'counts'    => [
                'pending'   => collect(PurchaseRequest::OPEN_STATUSES)
                    ->sum(fn ($s) => (int) ($counts[$s]->total ?? 0)),
                'approved'  => (int) ($counts[PurchaseRequest::STATUS_APPROVED]->total ?? 0),
                'rejected'  => (int) ($counts[PurchaseRequest::STATUS_REJECTED]->total ?? 0),
                'cancelled' => (int) ($counts[PurchaseRequest::STATUS_CANCELLED]->total ?? 0),
                'mine'      => count($mineIds),
            ],
        ]);
    }

    public function show(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $purchaseRequest->load([
            'items.branch', 'items.project',
            'approvals.actor.basicData', 'approvals.role',
            'employee.basicData', 'creator.basicData', 'canceller.basicData',
        ]);

        $actorId = (int) session('user.id');

        return view('hr-general.purchase-request.show', [
            'request'      => $purchaseRequest,
            'signatures'   => $service->signatureColumns($purchaseRequest),
            'backRoute'    => route('general.purchase-request.index'),
            'printRoute'   => route('general.purchase-request.print', $purchaseRequest),

            // Tombol putusan hanya dirender bila pemegang slugnya. Apakah ia
            // benar-benar boleh menyetujui DOKUMEN INI ditentukan lapis kedua —
            // langkah persetujuan — yang dihitung di canAct().
            'canApprove'   => $this->can('general.purchase-request.approve'),
            'waitingForMe' => $service->canAct($purchaseRequest, $actorId, $this->canManage())['allowed'],
            'approveRoute' => route('general.purchase-request.approve', $purchaseRequest),
            'rejectRoute'  => route('general.purchase-request.reject', $purchaseRequest),

            // Tombol berikut hanya dirender bila pemegang slugnya. Dokumen yang
            // sudah terhapus tidak menawarkan ubah maupun hapus lagi.
            'editRoute'   => $this->canEditDocument($purchaseRequest, $service)
                ? route('general.purchase-request.edit', $purchaseRequest)
                : null,
            'deleteRoute' => $this->canManage() && !$purchaseRequest->trashed()
                ? route('general.purchase-request.destroy', $purchaseRequest)
                : null,
            'exportRoute' => $this->can('general.purchase-request.export')
                ? route('general.purchase-request.export.single', $purchaseRequest)
                : null,

            // Pembatalan adalah hak PEMOHON, bukan hak HR (Keputusan D131). HR
            // yang ingin menghentikan dokumen memakai Reject — yang menuntut
            // alasan tertulis dan tercatat di jejak persetujuan.
            'cancelRoute'  => null,
        ]);
    }

    public function print(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        // Dokumen yang dicetak HR dan yang dicetak karyawan HARUS identik, jadi
        // keduanya memakai satu perender yang sama.
        return MyPurchaseRequestController::renderPrint($purchaseRequest, $service);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $service->approve(
            $purchaseRequest,
            (int) session('user.id'),
            $validated,
            $this->canManage()
        );

        if (!$result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return back()->with('success', $result['completed']
            ? 'Purchase request ' . $purchaseRequest->request_no . ' fully approved.'
            : 'Step approved. The request moved on to the next approver.');
    }

    public function reject(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        // Catatan WAJIB saat menolak. Penolakan tanpa alasan hanya memindahkan
        // pertanyaan pemohon ke jalur lain — biasanya ke meja HR langsung.
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'notes.required' => 'Please explain why the purchase request is rejected.',
        ]);

        $result = $service->reject(
            $purchaseRequest,
            (int) session('user.id'),
            $validated['notes'],
            $this->canManage()
        );

        return $result['allowed']
            ? back()->with('success', 'Purchase request rejected.')
            : back()->with('error', $result['reason']);
    }

    // ── Membuat atas nama karyawan ("New PR") ───────────────────────────────

    public function create(PurchaseRequestService $service)
    {
        $settings = PurchaseRequestSetting::current();
        $today    = now()->startOfDay();
        $steps    = $service->activeSteps();

        return view('hr-general.purchase-request.form', array_merge([
            'mode'        => 'create',
            'request'     => null,
            'settings'    => $settings,
            'costCenters' => $service->costCenterOptions(),
            'steps'       => $steps,
            'employees'   => $this->employeeOptions(),
            'action'      => route('general.purchase-request.store'),
            'backRoute'   => route('general.purchase-request.index'),

            // Pembuat dokumen memegang hak buat, sehingga batas mundur dan
            // periode terkunci tidak menghalanginya (lihat checkDateRules()).
            // Batas ke depan tetap mengikuti setelan.
            'minDate'     => null,
            'maxDate'     => $settings->allow_future_date ? null : $today->toDateString(),
        ], $this->approverChoice($steps)));
    }

    public function store(Request $request, PurchaseRequestService $service)
    {
        $data = $this->validateDocument($request, $service, true);

        $result = $service->submit(
            (int) $data['employee_id'],
            $data,
            (int) session('user.id')          // pembuatnya dicatat di created_by
        );

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.purchase-request.show', $result['request'])
            ->with('success', 'Purchase request ' . $result['request']->request_no
                . ' created on behalf of the employee.');
    }

    // ── Mengubah dokumen berjalan ───────────────────────────────────────────

    public function edit(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        if (!$this->canEditDocument($purchaseRequest, $service)) {
            return redirect()
                ->route('general.purchase-request.show', $purchaseRequest)
                ->with('error', $this->editRefusalReason($purchaseRequest));
        }

        $purchaseRequest->load(['items', 'employee.basicData']);

        return view('hr-general.purchase-request.form', array_merge([
            'mode'        => 'edit',
            'request'     => $purchaseRequest,
            'settings'    => PurchaseRequestSetting::current(),
            'costCenters' => $service->costCenterOptions(),
            'steps'       => $service->activeSteps(),
            'employees'   => $this->employeeOptions(),
            'action'      => route('general.purchase-request.update', $purchaseRequest),
            'backRoute'   => route('general.purchase-request.show', $purchaseRequest),
            'minDate'     => null,
            'maxDate'     => null,
        ], [
            // Alur dokumen ini sudah dibekukan saat dibuat; menanyakan approver
            // lagi akan menyiratkan bahwa penyetujunya masih bisa diganti.
            'firstStep'          => null,
            'chooseApprover'     => false,
            'approverCandidates' => [],
        ]));
    }

    public function update(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        // 🔴 Diperiksa ULANG di sini, bukan hanya saat merender form. Rute
        // `update` dijaga slug halaman, bukan `.manage`, karena penyetuju yang
        // diizinkan menyesuaikan item masuk lewat rute yang sama — tanpa
        // pemeriksaan ini ia dapat menembak endpoint-nya langsung setelah
        // gilirannya lewat.
        if (!$this->canEditDocument($purchaseRequest, $service)) {
            return redirect()
                ->route('general.purchase-request.show', $purchaseRequest)
                ->with('error', $this->editRefusalReason($purchaseRequest));
        }

        $data = $this->validateDocument($request, $service, false);

        $result = $service->update($purchaseRequest, $data, (int) session('user.id'));

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.purchase-request.show', $purchaseRequest)
            ->with('success', 'Purchase request ' . $purchaseRequest->request_no . ' updated.');
    }

    public function destroy(Request $request, PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'delete_reason.required' => 'Please state why this document is being deleted.',
        ]);

        $result = $service->softDelete(
            $purchaseRequest,
            (int) session('user.id'),
            $validated['delete_reason']
        );

        if (!$result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.purchase-request.index')
            ->with('success', 'Purchase request ' . $purchaseRequest->request_no
                . ' deleted. It stays on record and can be found under Status → Deleted.');
    }

    // ── Ekspor ──────────────────────────────────────────────────────────────

    /** Satu dokumen. */
    public function exportSingle(PurchaseRequest $purchaseRequest, PurchaseRequestService $service)
    {
        $purchaseRequest->load([
            'items.branch', 'items.project', 'approvals.actor.basicData', 'employee.basicData',
        ]);

        return $this->download(
            collect([$purchaseRequest]),
            $service,
            'Purchase Request ' . $purchaseRequest->request_date->format('Y-m'),
            'purchase_request_' . str_replace('/', '-', $purchaseRequest->request_no)
        );
    }

    /**
     * Seluruh dokumen pada satu bulan ("Monthly Export").
     *
     * Memakai query yang SAMA dengan layar, hanya tanpa paginasi, supaya isi
     * berkas selalu sama dengan yang dilihat pengguna (Keputusan D48). Dokumen
     * terhapus karena itu ikut hanya bila filternya memang Deleted.
     */
    public function export(Request $request, PurchaseRequestService $service)
    {
        $filters = $this->filters($request);

        $rows = $this->baseQuery($filters)
            ->with(['items.branch', 'items.project', 'approvals.actor.basicData', 'employee.basicData'])
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        $period = $filters['month'] !== '' ? $filters['month'] : now()->format('Y-m');

        return $this->download(
            $rows,
            $service,
            'Purchase Request ' . $period,
            'purchase_request_' . $period
        );
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function download($rows, PurchaseRequestService $service, string $sheet, string $file)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\PurchaseRequestDocumentExport($rows, $service, $sheet),
            $file . '.xlsx'
        );
    }

    /** Karyawan aktif untuk dropdown "New PR". */
    private function employeeOptions()
    {
        return Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->sortBy(fn ($e) => $e->basicData?->nick_name ?? $e->eci)
            ->values();
    }

    /**
     * Data dropdown "Approver" pada form New PR (Keputusan D126).
     *
     * Bentuknya sama dengan MyPurchaseRequestController::approverChoice() —
     * hanya langkah PERTAMA yang ditanyakan, dan hanya bila langkah itu
     * benar-benar menawarkan pilihan.
     *
     * @return array{firstStep: ?object, chooseApprover: bool, approverCandidates: array}
     */
    private function approverChoice($steps): array
    {
        $first = $steps->first();

        if (!$first || !$first->offersChoice()) {
            return ['firstStep' => $first, 'chooseApprover' => false, 'approverCandidates' => []];
        }

        $candidates = Employee::with('basicData:basic_data_id,employee_id,nick_name')
            ->whereIn('employee_id', $first->candidateEmployeeIds())
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->map(fn (Employee $e) => [
                'id'   => (int) $e->employee_id,
                'name' => $e->basicData?->nick_name ?? $e->eci,
            ])
            ->sortBy('name')
            ->values()
            ->all();

        return [
            'firstStep'          => $first,
            'chooseApprover'     => $candidates !== [],
            'approverCandidates' => $candidates,
        ];
    }

    /**
     * Validasi dokumen untuk "New PR" dan Edit.
     *
     * Aturan baris item diambil dari service supaya ketiga pintu masuk —
     * pengajuan mandiri, New PR, dan Edit — menilai isi dokumen dengan aturan
     * yang persis sama.
     */
    private function validateDocument(Request $request, PurchaseRequestService $service, bool $needsEmployee): array
    {
        $settings = PurchaseRequestSetting::current();
        $minTitle = $settings->require_title_min_chars;

        // Field gabungan `cost_center` dari dropdown Charged To dipecah dulu jadi
        // bentuk lama (cost_center_type/branch_id/delivery_project_id) sebelum
        // menyentuh aturan dan service yang sudah teruji.
        $request->merge(['items' => $service->expandCostCenterInput($request->input('items'))]);

        $rules = $service->itemRules($settings, $request->all()) + [
            'request_date'   => ['required', 'date'],
            'title'          => ['required', 'string', 'min:' . $minTitle, 'max:200'],
            'notes'          => ['nullable', 'string', 'max:2000'],
            'approver_ids'   => ['nullable', 'array'],
            'approver_ids.*' => ['integer', 'exists:employee,employee_id'],
        ];

        if ($needsEmployee) {
            $rules['employee_id'] = ['required', 'integer', 'exists:employee,employee_id'];
        }

        return $request->validate($rules, [
            'employee_id.required'              => 'Choose which employee this request belongs to.',
            'items.required'                    => 'Add at least one request item.',
            'items.*.description.required'      => 'Every item needs a description.',
            'items.*.qty.required'              => 'Every item needs a quantity.',
            'items.*.qty.min'                   => 'Item quantities must be greater than zero.',
            'items.*.unit.required'             => 'Every item needs a unit.',
            'items.*.unit.in'                   => 'That unit is not allowed. Allowed units: '
                                                   . implode(', ', $settings->unitOptions()) . '.',
            'items.*.cost_center_type.required' => 'Every item needs a cost center type.',
            'items.*.use_date.required'         => 'Every item needs a use date.',
            'items.*.period_from.required'      => 'Every item needs a period.',
            'title.min'                         => "Please describe the request in at least {$minTitle} characters.",
        ]);
    }

    /** Pesan yang menyebut sebab penolakan, bukan sekadar "tidak boleh". */
    private function editRefusalReason(PurchaseRequest $request): string
    {
        if ($request->trashed()) {
            return 'This document has been deleted and can no longer be edited.';
        }

        if (!$request->isEditable()) {
            return 'This document is already ' . $request->status . ' and can no longer be edited.';
        }

        if (!$this->canManage() && !PurchaseRequestSetting::current()->allow_approver_adjust_items) {
            return 'Approvers are not allowed to adjust items. HR can turn that on in Purchase Request Settings.';
        }

        return 'You can only edit a request while it is waiting for your approval step.';
    }

    /** @return array<string, string> */
    protected function filters(Request $request): array
    {
        return [
            'status' => (string) $request->query('status', 'open'),
            'scope'  => (string) $request->query('scope', 'all'),
            'month'  => (string) $request->query('month', ''),
            'search' => trim((string) $request->query('search', '')),
        ];
    }

    protected function baseQuery(array $filters)
    {
        return PurchaseRequest::query()
            ->with(['employee.basicData', 'approvals.actor.basicData', 'approvals.role', 'items'])

            ->when($filters['status'] === 'open',
                fn ($q) => $q->whereIn('status', PurchaseRequest::OPEN_STATUSES))

            // `cancelled` TIDAK digabung ke `rejected`: keduanya beda sebab —
            // yang satu ditarik pemohon sebelum ditinjau, yang lain diputuskan
            // penyetuju. Menggabungkannya membuat rekap tidak dapat menjawab
            // "berapa yang ditolak?".
            ->when(!in_array($filters['status'], ['open', 'all', 'deleted'], true),
                fn ($q) => $q->where('status', $filters['status']))

            // Dokumen terhapus hanya dapat dilihat pemegang hak kelola
            // (Keputusan D109) — ia tetap ada demi audit, bukan demi rekap harian.
            ->when($filters['status'] === 'deleted' && $this->canManage(),
                fn ($q) => $q->onlyTrashed())

            ->when($filters['month'] !== '', function ($q) use ($filters) {
                [$year, $month] = array_pad(explode('-', $filters['month']), 2, null);

                $q->whereYear('request_date', (int) $year)
                  ->whereMonth('request_date', (int) $month);
            })
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = $filters['search'];

                $q->where(function ($inner) use ($term) {
                    $inner->where('request_no', 'like', "%{$term}%")
                          ->orWhere('title', 'like', "%{$term}%")
                          ->orWhereHas('employee', fn ($e) => $e->where('eci', 'like', "%{$term}%"))
                          ->orWhereHas('employee.basicData', fn ($b) => $b->where('nick_name', 'like', "%{$term}%"));
                });
            });
    }

    /** Apakah pengguna aktif memegang izin mengelola (ubah kapan pun / hapus)? */
    protected function canManage(): bool
    {
        return $this->can('general.purchase-request.manage');
    }

    /**
     * Bolehkah pengguna aktif mengubah dokumen INI?
     *
     * Ada DUA jalan sah, dan keduanya disengaja:
     *
     *  1. Pemegang `general.purchase-request.manage` — kapan pun, selama
     *     dokumennya masih terbuka. Ini peran HR yang merapikan dokumen orang lain.
     *
     *  2. PENYETUJU YANG SEDANG MENDAPAT GILIRAN, dan hanya bila pengaturan
     *     `allow_approver_adjust_items` dinyalakan. Inilah yang membuat kotak
     *     centang "Approvers may adjust items" di halaman Settings benar-benar
     *     berarti — setelan yang tersimpan tetapi tidak dibaca satu baris kode
     *     pun adalah kegagalan D52.
     *
     * Batasnya ketat: hanya selama dokumen menunggu DIRINYA. Begitu ia
     * menyetujui, gilirannya lewat dan haknya ikut hilang.
     *
     * 🔴 Sudah ditulis di P5 meski tombolnya baru dipasang di P6: keputusannya
     * dipakai DUA KALI — saat merender tombol dan saat menyimpan — dan menaruh
     * keduanya di satu method sejak awal mencegah keduanya menyimpang.
     */
    protected function canEditDocument(PurchaseRequest $request, PurchaseRequestService $service): bool
    {
        if (!$request->isEditable() || $request->trashed()) {
            return false;
        }

        if ($this->canManage()) {
            return true;
        }

        if (!PurchaseRequestSetting::current()->allow_approver_adjust_items) {
            return false;
        }

        if (!$this->can('general.purchase-request.approve')) {
            return false;
        }

        return $service->canAct($request, (int) session('user.id'), false)['allowed'];
    }

    /**
     * Karyawan yang sedang aktif, di-cache selama umur controller ini.
     *
     * 🔴 SENGAJA properti INSTANCE, bukan `static` di dalam method seperti pada
     * ReimbursementController dan OvertimeReviewController.
     *
     * `static $employee` di dalam sebuah method dibagi oleh SELURUH instance
     * kelas itu dalam satu proses PHP — bukan per instance. Pada PHP-FPM biasa
     * hal itu tidak pernah terlihat, karena tiap request memakai proses yang
     * bersih. Tetapi begitu prosesnya berumur panjang — worker antrean, perintah
     * artisan yang melayani lebih dari satu pengguna, atau skrip pengujian —
     * izin pengguna PERTAMA ikut terbawa ke pengguna berikutnya. Itu bukan
     * sekadar cache basi: itu satu pengguna memakai hak akses orang lain.
     *
     * Ditemukan saat menulis uji asap P5: membuat instance controller baru untuk
     * "berganti pengguna" ternyata tidak mengganti apa pun.
     *
     * Controller modul lain sengaja TIDAK ikut diubah di sini — keduanya sudah
     * selesai, teruji, dan menuju produksi. Perubahan pada keduanya harus
     * diminta terpisah.
     */
    private ?Employee $actor = null;
    private bool $actorLoaded = false;

    /**
     * Pemeriksaan slug untuk pengguna aktif.
     *
     * Memakai Employee::canAccessMenu() yang sudah ada — bukan memperkenalkan
     * mekanisme izin baru.
     */
    protected function can(string $slug): bool
    {
        if (!$this->actorLoaded) {
            $this->actor       = Employee::where('employee_id', session('user.id'))->first();
            $this->actorLoaded = true;
        }

        return $this->actor?->canAccessMenu($slug) ?? false;
    }
}
