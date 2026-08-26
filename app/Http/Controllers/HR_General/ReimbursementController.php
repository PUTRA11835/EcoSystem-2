<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Reimbursement\ReimbursementRequest;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;

/**
 * Pengelolaan reimbursement (sisi HR / GA / penyetuju).
 *
 * Pemisahan izin yang sengaja tidak digabung, meniru Overtime (Keputusan D77):
 *   general.reimbursement          boleh MEMBUKA halaman dan membaca dokumen
 *   general.reimbursement.approve  boleh bertindak pada langkah yang menunggu DIRINYA
 *   general.reimbursement.manage   boleh MENGUBAH dan MENGHAPUS dokumen
 *   general.reimbursement.export   boleh mengunduh Excel
 *
 * Tanpa pemisahan itu, memberi hak meninjau otomatis memberi hak menghapus
 * dokumen yang berujung ke pembayaran.
 */
class ReimbursementController extends Controller
{
    public function index(Request $request, ReimbursementService $reimbursement)
    {
        $filters = $this->filters($request);
        $actorId = (int) session('user.id');

        // Id yang menjadi giliran orang ini. Dihitung sekali lalu dipakai untuk
        // menandai baris DAN untuk menyaring — supaya penyetuju tidak perlu
        // menebak mana yang menunggu dirinya.
        $mineIds = $reimbursement->pendingIdsFor($actorId);

        $requests = $this->baseQuery($filters)
            ->when($filters['scope'] === 'mine', fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->orderByRaw("FIELD(status, 'submitted', 'in_review') DESC")
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        // Hanya jumlah dokumen per status. Total nominal sengaja TIDAK dihitung
        // di sini: kartunya sudah dihapus dari layar, dan angka yang tidak
        // ditampilkan tetap membebani query di setiap pembukaan halaman.
        $counts = ReimbursementRequest::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Hak mengubah dihitung PER DOKUMEN, bukan sekali untuk seluruh halaman:
        // penyetuju yang diizinkan menyesuaikan nominal hanya boleh menyentuh
        // dokumen yang sedang menunggu dirinya (lihat canEditDocument()).
        $editableIds = $requests->getCollection()
            ->filter(fn ($r) => $this->canEditDocument($r, $reimbursement))
            ->pluck('id')
            ->all();

        return view('HR_General.reimbursement.index', [
            'requests'    => $requests,
            'filters'     => $filters,
            'mineIds'     => $mineIds,
            'editableIds' => $editableIds,
            'settings'    => ReimbursementSetting::current(),
            'canManage'   => $this->canManage(),
            'counts'    => [
                'pending'  => collect(ReimbursementRequest::OPEN_STATUSES)
                    ->sum(fn ($s) => (int) ($counts[$s]->total ?? 0)),
                'approved' => (int) ($counts[ReimbursementRequest::STATUS_APPROVED]->total ?? 0),
                'rejected' => (int) ($counts[ReimbursementRequest::STATUS_REJECTED]->total ?? 0),
                'mine'     => count($mineIds),
            ],
        ]);
    }

    public function show(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $reimbursementRequest->load([
            'items.branch', 'approvals.actor.basicData', 'approvals.role',
            'employee.basicData', 'creator.basicData',
        ]);

        $actorId = (int) session('user.id');

        return view('HR_General.reimbursement.show', [
            'request'      => $reimbursementRequest,
            'signatories'  => $reimbursement->signatories($reimbursementRequest),
            'backRoute'    => route('general.reimbursement.index'),
            'printRoute'   => route('general.reimbursement.print', $reimbursementRequest),

            // Tombol putusan hanya dirender bila pemegang slugnya. Apakah ia
            // benar-benar boleh menyetujui DOKUMEN INI ditentukan lapis kedua —
            // langkah persetujuan — yang dihitung di canAct().
            'canApprove'   => $this->can('general.reimbursement.approve'),
            'waitingForMe' => $reimbursement->canAct($reimbursementRequest, $actorId, $this->canManage())['allowed'],
            'approveRoute' => route('general.reimbursement.approve', $reimbursementRequest),
            'rejectRoute'  => route('general.reimbursement.reject', $reimbursementRequest),

            // Tombol berikut hanya dirender bila pemegang slugnya. Dokumen yang
            // sudah terhapus tidak menawarkan ubah maupun hapus lagi.
            'editRoute'   => $this->canEditDocument($reimbursementRequest, $reimbursement)
                ? route('general.reimbursement.edit', $reimbursementRequest)
                : null,
            'deleteRoute' => $this->canManage() && !$reimbursementRequest->trashed()
                ? route('general.reimbursement.destroy', $reimbursementRequest)
                : null,
            'exportRoute' => $this->can('general.reimbursement.export')
                ? route('general.reimbursement.export.single', $reimbursementRequest)
                : null,
        ]);
    }

    public function print(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        // Dokumen yang dicetak HR dan yang dicetak karyawan HARUS identik, jadi
        // keduanya memakai satu perender yang sama.
        return MyReimbursementController::renderPrint($reimbursementRequest, $reimbursement);
    }

    public function approve(Request $request, ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        $result = $reimbursement->approve(
            $reimbursementRequest,
            (int) session('user.id'),
            $validated,
            $this->canManage()
        );

        if (!$result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return back()->with('success', $result['completed']
            ? 'Reimbursement ' . $reimbursementRequest->request_no . ' fully approved.'
            : 'Step approved. The document moved on to the next approver.');
    }

    public function reject(Request $request, ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        // Catatan WAJIB saat menolak. Penolakan tanpa alasan hanya memindahkan
        // pertanyaan karyawan ke jalur lain — biasanya ke meja HR langsung.
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'notes.required' => 'Please explain why the reimbursement is rejected.',
        ]);

        $result = $reimbursement->reject(
            $reimbursementRequest,
            (int) session('user.id'),
            $validated['notes'],
            $this->canManage()
        );

        return $result['allowed']
            ? back()->with('success', 'Reimbursement rejected.')
            : back()->with('error', $result['reason']);
    }

    // ── Membuat atas nama karyawan ("New RB") ───────────────────────────────

    public function create(ReimbursementService $reimbursement)
    {
        $settings = ReimbursementSetting::current();
        $today    = now()->startOfDay();

        return view('HR_General.reimbursement.form', [
            'mode'      => 'create',
            'request'   => null,
            'settings'  => $settings,
            'branches'  => $reimbursement->branchOptions(),
            'steps'     => $reimbursement->activeSteps(),
            'employees' => $this->employeeOptions(),
            'action'    => route('general.reimbursement.store'),
            'backRoute' => route('general.reimbursement.index'),

            // Pembuat dokumen memegang hak kelola, sehingga batas mundur dan
            // periode terkunci tidak menghalanginya. Batas tanggal ke depan
            // tetap berlaku: biaya yang belum terjadi belum punya nota.
            'minDate'   => null,
            'maxDate'   => $settings->allow_future_date ? null : $today->toDateString(),
        ]);
    }

    public function store(Request $request, ReimbursementService $reimbursement)
    {
        $data = $this->validateDocument($request, $reimbursement, true);

        $result = $reimbursement->submit(
            (int) $data['employee_id'],
            $data,
            (int) session('user.id')          // pembuatnya dicatat di created_by
        );

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.reimbursement.show', $result['request'])
            ->with('success', 'Reimbursement ' . $result['request']->request_no . ' created on behalf of the employee.');
    }

    // ── Mengubah dokumen berjalan ───────────────────────────────────────────

    public function edit(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        if (!$this->canEditDocument($reimbursementRequest, $reimbursement)) {
            return redirect()
                ->route('general.reimbursement.show', $reimbursementRequest)
                ->with('error', $this->editRefusalReason($reimbursementRequest));
        }

        $reimbursementRequest->load('items');

        return view('HR_General.reimbursement.form', [
            'mode'      => 'edit',
            'request'   => $reimbursementRequest,
            'settings'  => ReimbursementSetting::current(),
            'branches'  => $reimbursement->branchOptions(),
            'steps'     => $reimbursement->activeSteps(),
            'employees' => $this->employeeOptions(),
            'action'    => route('general.reimbursement.update', $reimbursementRequest),
            'backRoute' => route('general.reimbursement.show', $reimbursementRequest),
            'minDate'   => null,
            'maxDate'   => null,
        ]);
    }

    public function update(Request $request, ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        // 🔴 Diperiksa ULANG di sini, bukan hanya saat merender form. Rute
        // `update` dijaga slug `.manage`, tetapi penyetuju yang diizinkan
        // mengubah nominal masuk lewat rute yang sama — tanpa pemeriksaan ini
        // ia dapat menembak endpoint-nya langsung setelah gilirannya lewat.
        if (!$this->canEditDocument($reimbursementRequest, $reimbursement)) {
            return redirect()
                ->route('general.reimbursement.show', $reimbursementRequest)
                ->with('error', $this->editRefusalReason($reimbursementRequest));
        }

        $data = $this->validateDocument($request, $reimbursement, false);

        $result = $reimbursement->update($reimbursementRequest, $data, (int) session('user.id'));

        if (!$result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.reimbursement.show', $reimbursementRequest)
            ->with('success', 'Reimbursement ' . $reimbursementRequest->request_no . ' updated.');
    }

    public function destroy(Request $request, ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'min:3', 'max:255'],
        ], [
            'delete_reason.required' => 'Please state why this document is being deleted.',
        ]);

        $result = $reimbursement->softDelete(
            $reimbursementRequest,
            (int) session('user.id'),
            $validated['delete_reason']
        );

        if (!$result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.reimbursement.index')
            ->with('success', 'Reimbursement ' . $reimbursementRequest->request_no
                . ' deleted. It stays on record and can be found under Status → Deleted.');
    }

    // ── Ekspor ──────────────────────────────────────────────────────────────

    /** Satu dokumen. */
    public function exportSingle(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $reimbursementRequest->load(['items.branch', 'approvals.actor.basicData', 'employee.basicData']);

        return $this->download(
            collect([$reimbursementRequest]),
            $reimbursement,
            'Reimbursement ' . $reimbursementRequest->request_date->format('Y-m'),
            'reimbursement_' . str_replace('/', '-', $reimbursementRequest->request_no)
        );
    }

    /**
     * Seluruh dokumen pada satu bulan ("Monthly Export").
     *
     * Memakai query yang SAMA dengan layar, hanya tanpa paginasi, supaya isi
     * berkas selalu sama dengan yang dilihat pengguna (Keputusan D48).
     */
    public function export(Request $request, ReimbursementService $reimbursement)
    {
        $filters = $this->filters($request);

        $rows = $this->baseQuery($filters)
            ->with(['items.branch', 'approvals.actor.basicData', 'employee.basicData'])
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        $period = $filters['month'] !== '' ? $filters['month'] : now()->format('Y-m');

        return $this->download(
            $rows,
            $reimbursement,
            'Reimbursement ' . $period,
            'reimbursement_' . $period
        );
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function download($rows, ReimbursementService $reimbursement, string $sheet, string $file)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\ReimbursementDocumentExport($rows, $reimbursement, $sheet),
            $file . '.xlsx'
        );
    }

    /** Karyawan aktif untuk dropdown "New RB". */
    private function employeeOptions()
    {
        return Employee::with('basicData:basic_data_id,employee_id,nick_name,department')
            ->where('is_active', 1)
            ->get(['employee_id', 'eci'])
            ->sortBy(fn ($e) => $e->basicData?->nick_name ?? $e->eci)
            ->values();
    }

    /**
     * Validasi dokumen untuk "New RB" dan Edit.
     *
     * Aturan baris item diambil dari service supaya ketiga pintu masuk —
     * pengajuan mandiri, New RB, dan Edit — menilai isi dokumen dengan aturan
     * yang persis sama.
     */
    private function validateDocument(Request $request, ReimbursementService $reimbursement, bool $needsEmployee): array
    {
        $settings = ReimbursementSetting::current();
        $minTitle = $settings->require_title_min_chars;

        $rules = $reimbursement->itemRules($settings, $request->all()) + [
            'request_date' => ['required', 'date'],
            'title'        => ['required', 'string', 'min:' . $minTitle, 'max:200'],
            'supporting_url' => array_filter([
                $settings->require_supporting_url ? 'required' : 'nullable',
                'url',
                'max:1000',
            ]),
        ];

        if ($needsEmployee) {
            $rules['employee_id'] = ['required', 'integer', 'exists:employee,employee_id'];
        }

        return $request->validate($rules, [
            'employee_id.required'         => 'Choose which employee this reimbursement belongs to.',
            'items.required'               => 'Add at least one reimbursement item.',
            'items.*.description.required' => 'Every item needs a description.',
            'items.*.branch_id.required'   => 'Every item needs a branch.',
            'items.*.amount.min'           => 'Item amounts must be greater than zero.',
            'title.min'                    => "Please describe the reimbursement in at least {$minTitle} characters.",
        ]);
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
        return ReimbursementRequest::query()
            ->with(['employee.basicData', 'approvals.actor.basicData', 'approvals.role', 'items'])
            ->when($filters['status'] === 'open',
                fn ($q) => $q->whereIn('status', ReimbursementRequest::OPEN_STATUSES))
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
        return $this->can('general.reimbursement.manage');
    }

    /**
     * Bolehkah pengguna aktif mengubah dokumen INI?
     *
     * Ada DUA jalan sah, dan keduanya disengaja:
     *
     *  1. Pemegang `general.reimbursement.manage` — kapan pun, selama dokumennya
     *     masih terbuka. Ini peran HR yang merapikan dokumen orang lain.
     *
     *  2. PENYETUJU YANG SEDANG MENDAPAT GILIRAN, dan hanya bila pengaturan
     *     `allow_approver_adjust_amount` dinyalakan. Inilah yang membuat
     *     kotak centang "Approvers may adjust amounts" di halaman Settings
     *     benar-benar berarti. Sebelum ini kotak itu tersimpan tetapi tidak
     *     dibaca satu baris kode pun — persis kegagalan Keputusan D52, yaitu
     *     halaman pengaturan yang nilainya diabaikan mesin.
     *
     * Batasnya ketat: hanya selama dokumen menunggu DIRINYA. Begitu ia menyetujui,
     * gilirannya lewat dan haknya ikut hilang — jadi ia tidak dapat mengubah
     * angka pada dokumen yang sudah diteruskan ke langkah berikutnya.
     *
     * Setiap perubahan nominal tetap ditandai `amount_adjusted` dan dicatat ke
     * log oleh ReimbursementService::update(), siapa pun yang melakukannya.
     */
    protected function canEditDocument(ReimbursementRequest $request, ReimbursementService $reimbursement): bool
    {
        if (!$request->isEditable() || $request->trashed()) {
            return false;
        }

        if ($this->canManage()) {
            return true;
        }

        if (!ReimbursementSetting::current()->allow_approver_adjust_amount) {
            return false;
        }

        if (!$this->can('general.reimbursement.approve')) {
            return false;
        }

        return $reimbursement->canAct($request, (int) session('user.id'), false)['allowed'];
    }

    /** Pesan yang menyebut sebab penolakan, bukan sekadar "tidak boleh". */
    private function editRefusalReason(ReimbursementRequest $request): string
    {
        if ($request->trashed()) {
            return 'This document has been deleted and can no longer be edited.';
        }

        if (!$request->isEditable()) {
            return 'This document is already closed and can no longer be edited.';
        }

        if (!$this->canManage() && !ReimbursementSetting::current()->allow_approver_adjust_amount) {
            return 'Approvers are not allowed to adjust amounts. HR can turn that on in Reimbursement Settings.';
        }

        return 'You can only edit a document while it is waiting for your approval step.';
    }

    /**
     * Pemeriksaan slug untuk pengguna aktif.
     *
     * Memakai Employee::canAccessMenu() yang sudah ada, sama seperti
     * OvertimeReviewController — bukan memperkenalkan mekanisme izin baru.
     */
    protected function can(string $slug): bool
    {
        static $employee = null;

        if ($employee === null) {
            $employee = Employee::where('employee_id', session('user.id'))->first() ?: false;
        }

        return $employee ? $employee->canAccessMenu($slug) : false;
    }
}
