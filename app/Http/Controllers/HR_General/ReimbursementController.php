<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\HandlesRecruitmentTables;
use App\Models\Employee;
use App\Models\Reimbursement\ReimbursementRequest;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Services\Reimbursement\ReimbursementService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
    use HandlesRecruitmentTables;

    /** Filter yang tampil di kepala kolom; dipakai juga untuk "Reset Filter". */
    private const COLUMN_FILTERS = ['number', 'title', 'requester', 'date_from', 'date_to', 'amount_min', 'amount_max'];

    public function index(Request $request, ReimbursementService $reimbursement)
    {
        $filters = $this->filters($request);
        $actorId = (int) session('user.id');

        // Id yang menjadi giliran orang ini. Dihitung sekali lalu dipakai untuk
        // menandai baris DAN untuk menyaring — supaya penyetuju tidak perlu
        // menebak mana yang menunggu dirinya.
        $mineIds = $reimbursement->pendingIdsFor($actorId);

        $perPage = $this->perPage($request);

        $requests = $this->baseQuery($filters)
            ->when($filters['scope'] === 'mine', fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->orderByRaw("FIELD(status, 'submitted', 'in_review') DESC")
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->paginate($perPage)
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

        return view('hr-general.reimbursement.index', [
            'requests'    => $requests,
            'filters'     => $filters,
            'hasFilters'  => $this->hasFilters($filters),

            // Bulan yang punya dokumen, untuk dropdown bulan di kepala tabel.
            'monthOptions' => ReimbursementRequest::query()
                ->selectRaw("DATE_FORMAT(request_date, '%Y-%m') AS ym")
                ->distinct()
                ->orderByDesc('ym')
                ->pluck('ym')
                ->mapWithKeys(fn ($ym) => [$ym => \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $ym . '-01')->format('F Y')])
                ->all(),
            'perPage'     => $perPage,
            'perPageOptions' => self::PER_PAGE_OPTIONS,
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

    /**
     * Ringkasan: angka bulan ini, tren enam bulan, antrean milik pengguna, dan
     * dokumen yang perlu perhatian. Semuanya dihitung dari dokumen yang tidak
     * terhapus; nominal hanya untuk dokumen IDR agar tidak mencampur mata uang.
     */
    public function dashboard(ReimbursementService $reimbursement)
    {
        $actorId = (int) session('user.id');
        $mineIds = $reimbursement->pendingIdsFor($actorId);

        $monthStart = now()->startOfMonth();
        $idr        = ReimbursementRequest::DEFAULT_CURRENCY;

        $thisMonth = ReimbursementRequest::query()->where('request_date', '>=', $monthStart->toDateString());

        $byStatus = ReimbursementRequest::query()
            ->selectRaw('status, COUNT(*) AS total, COALESCE(SUM(CASE WHEN currency = ? THEN total_amount ELSE 0 END), 0) AS amount', [$idr])
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $openStatuses = ReimbursementRequest::OPEN_STATUSES;

        // Rata-rata hari dari dibuat sampai disetujui, 90 hari terakhir.
        $avgDays = ReimbursementRequest::query()
            ->where('status', ReimbursementRequest::STATUS_APPROVED)
            ->whereNotNull('completed_at')
            ->where('completed_at', '>=', now()->subDays(90))
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, completed_at)) / 24 AS days')
            ->value('days');

        $trend = collect(range(5, 0))->map(function ($ago) use ($idr) {
            $start = now()->startOfMonth()->subMonths($ago);
            $end   = $start->copy()->endOfMonth();

            $rows = ReimbursementRequest::query()->whereBetween('request_date', [$start->toDateString(), $end->toDateString()]);

            return [
                'label'  => $start->format('M Y'),
                'count'  => (clone $rows)->count(),
                'amount' => (float) (clone $rows)->where('currency', $idr)->sum('total_amount'),
            ];
        });

        $topRequesters = ReimbursementRequest::query()
            ->with('employee.basicData')
            ->where('request_date', '>=', $monthStart->toDateString())
            ->where('currency', $idr)
            ->selectRaw('employee_id, COUNT(*) AS docs, SUM(total_amount) AS amount')
            ->groupBy('employee_id')
            ->orderByDesc('amount')
            ->limit(5)
            ->get();

        $waiting = ReimbursementRequest::query()
            ->with('employee.basicData')
            ->whereIn('id', $mineIds ?: [0])
            ->orderBy('request_date')
            ->limit(6)
            ->get();

        $attention = ReimbursementRequest::query()
            ->with('employee.basicData')
            ->open()
            ->get()
            ->filter(fn ($r) => $r->attentionFlags() !== [])
            ->take(6)
            ->values();

        return view('hr-general.reimbursement.dashboard', [
            'monthLabel' => $monthStart->format('F Y'),
            'cards' => [
                'mine'      => count($mineIds),
                'pending'   => (int) collect($openStatuses)->sum(fn ($s) => $byStatus[$s]->total ?? 0),
                'pendingAmount' => (float) collect($openStatuses)->sum(fn ($s) => $byStatus[$s]->amount ?? 0),
                'month'     => (clone $thisMonth)->count(),
                'monthAmount' => (float) (clone $thisMonth)->where('currency', $idr)->sum('total_amount'),
                'approved'  => (int) ($byStatus[ReimbursementRequest::STATUS_APPROVED]->total ?? 0),
                'rejected'  => (int) ($byStatus[ReimbursementRequest::STATUS_REJECTED]->total ?? 0),
                'avgDays'   => $avgDays !== null ? round((float) $avgDays, 1) : null,
            ],
            'statusRows'    => [
                'Open'     => (int) collect($openStatuses)->sum(fn ($s) => $byStatus[$s]->total ?? 0),
                'Approved' => (int) ($byStatus[ReimbursementRequest::STATUS_APPROVED]->total ?? 0),
                'Rejected' => (int) ($byStatus[ReimbursementRequest::STATUS_REJECTED]->total ?? 0),
            ],
            'trend'         => $trend,
            'topRequesters' => $topRequesters,
            'waiting'       => $waiting,
            'attention'     => $attention,
        ]);
    }

    public function show(ReimbursementRequest $reimbursementRequest, ReimbursementService $reimbursement)
    {
        $reimbursementRequest->load([
            'items.branch', 'approvals.actor.basicData', 'approvals.role',
            'employee.basicData', 'creator.basicData', 'approver.basicData',
        ]);

        $actorId = (int) session('user.id');

        return view('hr-general.reimbursement.show', [
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

        return view('hr-general.reimbursement.form', [
            'mode'      => 'create',
            'request'   => null,
            'settings'  => $settings,
            'branches'  => $reimbursement->branchOptions(),
            'steps'     => $reimbursement->activeSteps(),

            // Semua karyawan aktif dapat menjadi pemohon. Orang pada langkah
            // bertanda "chosen by requester" dipilih dari kandidat langkahnya
            // (diatur di Settings → Approval Workflow).
            'requesters'  => $reimbursement->employeeOptions(),
            'choiceSteps' => $reimbursement->choiceSteps(),
            'fixedSteps'  => $reimbursement->fixedSteps(),
            'currencies' => ReimbursementRequest::CURRENCIES,
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

        $reimbursementRequest->load(['items', 'employee.basicData', 'approvals.actor.basicData']);

        return view('hr-general.reimbursement.form', [
            'mode'      => 'edit',
            'request'   => $reimbursementRequest,
            'settings'  => ReimbursementSetting::current(),
            'branches'  => $reimbursement->branchOptions(),
            'steps'     => $reimbursement->activeSteps(),
            'signatories' => $reimbursement->signatories($reimbursementRequest),
            'currencies' => ReimbursementRequest::CURRENCIES,
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

        // Tanpa rentang tanggal, "Monthly Export" berarti bulan berjalan —
        // bukan seluruh dokumen yang berlabel bulan berjalan.
        if ($filters['date_from'] === '' && $filters['date_to'] === '') {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to']   = now()->endOfMonth()->toDateString();
        }

        $rows = $this->baseQuery($filters)
            ->with(['items.branch', 'approvals.actor.basicData', 'employee.basicData'])
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        $period = $filters['date_from'] !== ''
            ? substr($filters['date_from'], 0, 7)
            : now()->format('Y-m');

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
            $rules['currency']    = ['required', 'string', Rule::in(ReimbursementRequest::CURRENCIES)];

            foreach ($reimbursement->activeSteps()->where('requester_selectable', true) as $step) {
                $rules['step_approver.' . $step->id] = [
                    'required', 'integer', Rule::in($step->candidateEmployeeIds()),
                ];
            }
        }

        return $request->validate($rules, [
            'employee_id.required'         => 'Choose which employee this reimbursement belongs to.',
            'step_approver.*.required'     => 'Choose who will act on every approval step marked for selection.',
            'step_approver.*.in'           => 'The chosen person is not one of the candidates of that step.',
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
        $text   = fn (string $key) => trim((string) $request->query($key, ''));
        $amount = fn (string $key) => preg_replace('/\D/', '', (string) $request->query($key, ''));

        $filters = [
            'status'     => (string) $request->query('status', 'open'),
            'scope'      => (string) $request->query('scope', 'all'),
            'month'      => (string) $request->query('month', ''),
            'search'     => $text('search'),
            'number'     => $text('number'),
            'title'      => $text('title'),
            'requester'  => $text('requester'),
            'date_from'  => $text('date_from'),
            'date_to'    => $text('date_to'),
            'amount_min' => $amount('amount_min'),
            'amount_max' => $amount('amount_max'),
        ];

        // `month` (YYYY-MM) lama tetap dipahami: tautan tersimpan dan impor
        // masih memakainya. Ia diterjemahkan jadi rentang tanggal.
        if ($filters['month'] !== '' && $filters['date_from'] === '' && $filters['date_to'] === ''
            && preg_match('/^\d{4}-\d{2}$/', $filters['month'])) {

            $start = \Illuminate\Support\Carbon::createFromFormat('Y-m-d', $filters['month'] . '-01')->startOfDay();

            $filters['date_from'] = $start->toDateString();
            $filters['date_to']   = $start->copy()->endOfMonth()->toDateString();
        }

        return $filters;
    }

    /** Apakah ada filter kolom yang aktif (menentukan tombol "Reset Filter")? */
    protected function hasFilters(array $filters): bool
    {
        foreach (self::COLUMN_FILTERS as $key) {
            if ($filters[$key] !== '') {
                return true;
            }
        }

        return $filters['scope'] === 'mine' || $filters['status'] !== 'open' || $filters['search'] !== '';
    }

    protected function baseQuery(array $filters)
    {
        return ReimbursementRequest::query()
            ->with(['employee.basicData', 'approver.basicData', 'approvals.actor.basicData', 'approvals.role', 'items'])
            ->when($filters['status'] === 'open',
                fn ($q) => $q->whereIn('status', ReimbursementRequest::OPEN_STATUSES))
            ->when(!in_array($filters['status'], ['open', 'all', 'deleted'], true),
                fn ($q) => $q->where('status', $filters['status']))

            // Dokumen terhapus hanya dapat dilihat pemegang hak kelola
            // (Keputusan D109) — ia tetap ada demi audit, bukan demi rekap harian.
            ->when($filters['status'] === 'deleted' && $this->canManage(),
                fn ($q) => $q->onlyTrashed())

            ->when($filters['date_from'] !== '', fn ($q) => $q->whereDate('request_date', '>=', $filters['date_from']))
            ->when($filters['date_to'] !== '', fn ($q) => $q->whereDate('request_date', '<=', $filters['date_to']))
            ->when($filters['amount_min'] !== '', fn ($q) => $q->where('total_amount', '>=', (float) $filters['amount_min']))
            ->when($filters['amount_max'] !== '', fn ($q) => $q->where('total_amount', '<=', (float) $filters['amount_max']))
            ->when($filters['number'] !== '', fn ($q) => $q->where('request_no', 'like', '%' . $filters['number'] . '%'))
            ->when($filters['title'] !== '', function ($q) use ($filters) {
                $term = '%' . $filters['title'] . '%';

                $q->where(fn ($inner) => $inner->where('title', 'like', $term)
                    ->orWhereHas('items', fn ($i) => $i->where('description', 'like', $term)));
            })
            ->when($filters['requester'] !== '', fn ($q) => $this->whereRequester($q, $filters['requester']))
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = $filters['search'];

                $q->where(function ($inner) use ($term) {
                    $inner->where('request_no', 'like', "%{$term}%")
                          ->orWhere('title', 'like', "%{$term}%");

                    $this->whereRequester($inner, $term, true);
                });
            });
    }

    /**
     * Pemohon dicari lewat ECI, nama panggilan, atau nama lengkap.
     *
     * @param  bool  $or  true: digabung dengan OR pada query pemanggil.
     */
    private function whereRequester($query, string $term, bool $or = false): void
    {
        $like   = '%' . $term . '%';
        $method = $or ? 'orWhereHas' : 'whereHas';

        $query->{$method}('employee', function ($employee) use ($like) {
            $employee->where('eci', 'like', $like)
                ->orWhereHas('basicData', function ($basic) use ($like) {
                    $basic->where('nick_name', 'like', $like)
                        ->orWhereRaw("CONCAT(COALESCE(first_name,''), ' ', COALESCE(last_name,'')) LIKE ?", [$like]);
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
