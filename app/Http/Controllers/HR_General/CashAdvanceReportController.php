<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\BuildsCashAdvanceForms;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceReport;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Models\Employee;
use App\Services\CashAdvance\CashAdvanceReportService;
use App\Services\CashAdvance\CashAdvanceService;
use Illuminate\Http\Request;

/**
 * Pengelolaan pertanggungjawaban uang muka (sisi HR / Finance / penyetuju).
 *
 * Cermin CashAdvanceController, dengan SATU perbedaan yang menjadi alasan
 * seluruh sub-modul ini ada:
 *
 * 🔴 MENYETUJUI LANGKAH TERAKHIR CAR IKUT MENUTUP BUKU CA INDUKNYA — dalam
 * transaksi yang SAMA. `settlement_status`, `reported_amount`,
 * `outstanding_amount`, dan `settled_at` pada `cash_advances` diperbarui
 * sekaligus. Menyetujui laporan lalu gagal menandai uang mukanya selesai adalah
 * keadaan setengah jadi yang tidak boleh bisa terjadi. Mesinnya di
 * CashAdvanceReportService::syncCashAdvance().
 *
 * Pemisahan izin sama dengan CA (pola D77):
 *   general.cash-advance-report          membuka halaman & membaca dokumen
 *   general.cash-advance-report.approve  bertindak pada langkah yang menunggu DIRINYA
 *   general.cash-advance-report.manage   mengubah & menghapus
 *   general.cash-advance-report.export   mengunduh Excel
 *
 * Edit, hapus beralasan, New CAR, dan Export menyusul di R5 bersama rutenya.
 */
class CashAdvanceReportController extends Controller
{
    // approverChoice() · nameOf() · validasi badan form — dipakai BERSAMA
    // dengan MyCashAdvanceReportController.
    use BuildsCashAdvanceForms;

    public function index(Request $request, CashAdvanceReportService $service)
    {
        $filters = $this->filters($request);
        $actorId = (int) session('user.id');

        $mineIds = $service->pendingIdsFor($actorId);

        $reports = $this->baseQuery($filters)
            ->when($filters['scope'] === 'mine', fn ($q) => $q->whereIn('id', $mineIds ?: [0]))
            ->orderByRaw("FIELD(status, 'submitted', 'in_review') DESC")
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = CashAdvanceReport::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // Uang yang harus BERGERAK setelah laporan disetujui. Dua arah, dan
        // arahnya mudah tertukar — karena itu dihitung terpisah, bukan
        // dijumlahkan jadi satu angka yang tidak punya arti.
        $settled = CashAdvanceReport::query()->approved();

        return view('hr-general.cash-advance.report-hr-index', [
            'reports'    => $reports,
            'filters'    => $filters,
            'mineIds'    => $mineIds,
            'settings'   => CashAdvanceSetting::current(),
            'canManage'  => $this->canManage(),

            // Empat hak, empat slug (D77).
            'canCreate'  => $this->can('general.cash-advance-report.create'),
            'canExport'  => $this->can('general.cash-advance-report.export'),

            // 🔴 CA SELURUH karyawan yang boleh dilaporkan sekarang — pintu
            // masuk "New CAR". Disaring checkEligibility() (D154), bukan oleh
            // scope outstanding(), supaya setiap tombol yang tampil pasti
            // diterima server. Dihitung hanya bila haknya ada: halaman ini
            // dibuka penyetuju yang tidak boleh membuat dokumen apa pun.
            'reportable' => $this->can('general.cash-advance-report.create')
                ? $this->reportableAdvances($service)
                : collect(),

            'counts'     => [
                'pending'   => collect(CashAdvanceReport::OPEN_STATUSES)
                    ->sum(fn ($s) => (int) ($counts[$s]->total ?? 0)),
                'approved'  => (int) ($counts[CashAdvanceReport::STATUS_APPROVED]->total ?? 0),
                'rejected'  => (int) ($counts[CashAdvanceReport::STATUS_REJECTED]->total ?? 0),
                'mine'      => count($mineIds),
            ],
            'money' => [
                'refund' => (float) (clone $settled)
                    ->where('settlement_type', CashAdvanceReport::SETTLEMENT_REFUND)
                    ->sum('difference_amount'),
                'claim'  => abs((float) (clone $settled)
                    ->where('settlement_type', CashAdvanceReport::SETTLEMENT_CLAIM)
                    ->sum('difference_amount')),
            ],
        ]);
    }

    public function show(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $cashAdvanceReport->load([
            'cashAdvance', 'items.branch', 'items.project',
            'approvals.actor.basicData', 'approvals.role',
            'employee.basicData',
        ]);

        // DUA syarat, keduanya wajib: slug membuka pintunya, langkah menentukan
        // apakah dokumen ini menunggu dirinya.
        $canDecide = $this->can('general.cash-advance-report.approve')
            && $service->canAct($cashAdvanceReport, (int) session('user.id'), $this->canManage())['allowed'];

        // 🔴 `report-show.blade.php` dipakai ULANG, tidak dibuat ulang — pola
        // yang sama dengan `show.blade.php` milik CA di langkah A5.
        return view('hr-general.cash-advance.report-show', [
            'report'       => $cashAdvanceReport,
            'signatures'   => $service->signatureColumns($cashAdvanceReport),
            'backRoute'    => route('general.cash-advance-report.index'),
            'printRoute'   => route('general.cash-advance-report.print', $cashAdvanceReport),
            'advanceRoute' => route('general.cash-advance.show', $cashAdvanceReport->cash_advance_id),
            'canApprove'   => $canDecide,
            'approveRoute' => $canDecide ? route('general.cash-advance-report.approve', $cashAdvanceReport) : null,
            'rejectRoute'  => $canDecide ? route('general.cash-advance-report.reject', $cashAdvanceReport) : null,

            // Cancel adalah hak PEMOHON menarik laporannya sendiri; sisi HR
            // memakai Delete, yang menuntut alasan tertulis (D109).
            'cancelRoute'  => null,

            'editRoute'    => $this->canEditDocument($cashAdvanceReport)
                ? route('general.cash-advance-report.edit', $cashAdvanceReport)
                : null,

            'deleteRoute'  => $this->can('general.cash-advance-report.manage')
                && ! $cashAdvanceReport->trashed()
                ? route('general.cash-advance-report.destroy', $cashAdvanceReport)
                : null,

            'exportRoute'  => $this->can('general.cash-advance-report.export')
                ? route('general.cash-advance-report.export.single', $cashAdvanceReport)
                : null,
        ]);
    }

    /**
     * 🔴 Memanggil MyCashAdvanceReportController::renderPrint() — nol baris
     * disalin. Cetakan karyawan dan cetakan HR harus identik, dan uji asap
     * membuktikannya byte-per-byte.
     */
    public function print(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        return MyCashAdvanceReportController::renderPrint($cashAdvanceReport, $service);
    }

    public function approve(Request $request, CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $validated = $request->validate(['notes' => ['nullable', 'string', 'max:500']]);

        $result = $service->approve(
            $cashAdvanceReport,
            (int) session('user.id'),
            $validated,
            $this->canManage()
        );

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        if (! $result['completed']) {
            return back()->with('success',
                'Step approved. ' . $cashAdvanceReport->report_no . ' moved to the next reviewer.');
        }

        // Buku CA-nya baru saja ditutup di transaksi yang sama. Pesannya
        // menyebutkan akibat uangnya, bukan sekadar "berhasil" — itulah yang
        // sebenarnya ingin diketahui orang yang baru menekan tombolnya.
        $cashAdvanceReport->refresh();

        return back()->with('success',
            'Report ' . $cashAdvanceReport->report_no . ' approved. '
            . $cashAdvanceReport->settlementDirection() . '. '
            . 'Cash advance ' . $cashAdvanceReport->cashAdvance?->request_no . ' is now settled.');
    }

    public function reject(Request $request, CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'notes.required' => 'Please write why this report is rejected.',
            'notes.min'      => 'Please give at least 5 characters of reason.',
        ]);

        $result = $service->reject(
            $cashAdvanceReport,
            (int) session('user.id'),
            $validated['notes'],
            $this->canManage()
        );

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return back()->with('success',
            'Report ' . $cashAdvanceReport->report_no . ' was rejected. '
            . 'Its cash advance is waiting to be reported again.');
    }

    // ── Membuat atas nama karyawan ("New CAR") ──────────────────────────────

    /**
     * 🔴 BERBEDA DARI "New CA": tidak ada dropdown karyawan di sini.
     *
     * Sebuah CAR selalu lahir dari sebuah CA, dan pemiliknya SUDAH ditentukan
     * CA itu. Menanyakan "atas nama siapa?" akan membuka kemungkinan laporan
     * milik satu orang menempel pada uang muka orang lain — keadaan yang tidak
     * punya arti dan tidak dapat diperbaiki tanpa menghapus dokumennya.
     *
     * Karena itu pintu masuknya adalah CA-nya (`?ca=`), dan daftar CA yang
     * layak ditampilkan di halaman rekap.
     */
    public function create(Request $request, CashAdvanceReportService $service)
    {
        $advance = $this->resolveAdvance($request, $service);

        $settings = CashAdvanceSetting::current();
        $steps    = $service->activeSteps();

        return view('hr-general.cash-advance.report-submit', array_merge([
            'mode'          => 'create',
            'report'        => null,
            'advance'       => $advance,
            'settings'      => $settings,
            'costCenters'   => app(CashAdvanceService::class)->costCenterOptions(),
            'steps'         => $steps,
            'action'        => route('general.cash-advance-report.store'),
            'backRoute'     => route('general.cash-advance-report.index'),
            'advanceRoute'  => route('general.cash-advance.show', $advance),

            // Pemegang hak buat dianggap admin oleh checkDateRules(), jadi batas
            // mundur tidak berlaku. Batas ke depan tetap mengikuti setelan.
            'maxDate'       => $settings->allow_future_date ? null : now()->startOfDay()->toDateString(),

            // 🔴 Nama PEMILIK CA, bukan nama yang sedang login. Laporan ini
            // dibukukan atas namanya; menampilkan nama operator akan keliru.
            'requesterName' => $advance->employee?->basicData?->nick_name
                ?? $advance->employee?->eci ?? '—',
        ], $this->approverChoice($steps)));
    }

    public function store(Request $request, CashAdvanceReportService $service)
    {
        $advance = $this->resolveAdvance($request, $service);
        $data    = $this->validateCashAdvanceReportPayload($request);

        $result = $service->submit(
            $advance,
            (int) $advance->employee_id,     // 🔴 pemilik CA, bukan operator
            $data,
            (int) session('user.id')         // pembuatnya tercatat di created_by
        );

        if (! $result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.cash-advance-report.show', $result['report'])
            ->with('success', 'Cash advance report ' . $result['report']->report_no
                . ' created on behalf of the employee.');
    }

    // ── Mengubah laporan berjalan ───────────────────────────────────────────

    public function edit(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        if (! $this->canEditDocument($cashAdvanceReport)) {
            return redirect()
                ->route('general.cash-advance-report.show', $cashAdvanceReport)
                ->with('error', $this->editRefusalReason($cashAdvanceReport));
        }

        $cashAdvanceReport->load(['cashAdvance.employee.basicData', 'items', 'approvals']);

        return view('hr-general.cash-advance.report-submit', [
            'mode'    => 'edit',
            'report'  => $cashAdvanceReport,

            // 🔴 CA induknya TIDAK dapat dipindah. `advance_amount` sudah
            // dibekukan ke laporan ini saat dibuat (D139); menggantungkannya ke
            // uang muka lain akan membuat selisih yang sudah ditandatangani
            // berubah sendiri.
            'advance' => $cashAdvanceReport->cashAdvance,

            'settings'      => CashAdvanceSetting::current(),
            'costCenters'   => app(CashAdvanceService::class)->costCenterOptions(),
            'steps'         => $service->activeSteps(),
            'action'        => route('general.cash-advance-report.update', $cashAdvanceReport),
            'backRoute'     => route('general.cash-advance-report.show', $cashAdvanceReport),
            'advanceRoute'  => route('general.cash-advance.show', $cashAdvanceReport->cash_advance_id),
            'maxDate'       => null,
            'requesterName' => $cashAdvanceReport->cashAdvance?->employee?->basicData?->nick_name ?? '—',

            // Alurnya sudah dibekukan saat laporan dibuat.
            'firstStep'          => null,
            'chooseApprover'     => false,
            'approverCandidates' => [],
        ]);
    }

    public function update(Request $request, CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        // Diperiksa ULANG di sini, bukan hanya saat merender form.
        if (! $this->canEditDocument($cashAdvanceReport)) {
            return redirect()
                ->route('general.cash-advance-report.show', $cashAdvanceReport)
                ->with('error', $this->editRefusalReason($cashAdvanceReport));
        }

        $data   = $this->validateCashAdvanceReportPayload($request);
        $result = $service->update($cashAdvanceReport, $data, (int) session('user.id'));

        if (! $result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.cash-advance-report.show', $cashAdvanceReport)
            ->with('success', 'Cash advance report ' . $cashAdvanceReport->report_no . ' updated.');
    }

    /**
     * Hapus — SOFT DELETE beralasan (Keputusan D109).
     *
     * 🔴 Menghapus laporan MEMBUKA KEMBALI buku CA induknya, dan itu dikerjakan
     * service dalam transaksi yang sama. Laporan yang hilang tanpa membuka
     * kembali uang mukanya akan meninggalkan CA yang selamanya tampak sudah
     * dipertanggungjawabkan padahal tidak.
     */
    public function destroy(Request $request, CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'delete_reason.required' => 'Please state why this report is being deleted.',
            'delete_reason.min'      => 'Please give at least 5 characters of reason.',
        ]);

        $result = $service->softDelete(
            $cashAdvanceReport,
            (int) session('user.id'),
            $validated['delete_reason']
        );

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.cash-advance-report.index')
            ->with('success', 'Cash advance report ' . $cashAdvanceReport->report_no
                . ' deleted. Its cash advance is open for reporting again.');
    }

    // ── Ekspor ──────────────────────────────────────────────────────────────

    /** Satu dokumen. */
    public function exportSingle(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $cashAdvanceReport->load([
            'cashAdvance', 'items.branch', 'items.project',
            'approvals.actor.basicData', 'approvals.role', 'employee.basicData',
        ]);

        return $this->download(
            collect([$cashAdvanceReport]),
            $service,
            'Cash Advance Report ' . $cashAdvanceReport->report_date->format('Y-m'),
            'cash_advance_report_' . str_replace('/', '-', $cashAdvanceReport->report_no)
        );
    }

    /** Seluruh laporan pada satu bulan, memakai query yang SAMA dengan layar (D48). */
    public function export(Request $request, CashAdvanceReportService $service)
    {
        $filters = $this->filters($request);

        $rows = $this->baseQuery($filters)
            ->with(['cashAdvance', 'items.branch', 'items.project',
                    'approvals.actor.basicData', 'approvals.role', 'employee.basicData'])
            ->orderBy('report_date')
            ->orderBy('id')
            ->get();

        $period = $filters['month'] !== '' ? $filters['month'] : now()->format('Y-m');

        return $this->download(
            $rows,
            $service,
            'Cash Advance Report ' . $period,
            'cash_advance_report_' . $period
        );
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function download($rows, CashAdvanceReportService $service, string $sheet, string $file)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CashAdvanceReportDocumentExport($rows, $service, $sheet),
            $file . '.xlsx'
        );
    }

    /**
     * CA milik SIAPA PUN yang boleh dilaporkan sekarang.
     *
     * Kandidatnya diambil lebar (bukunya belum ditutup), lalu disaring oleh
     * satu-satunya pihak yang berwenang menjawabnya (D154). `with('reports')`
     * membuat penyaringan itu nol kueri tambahan per baris.
     *
     * @return \Illuminate\Support\Collection<int, CashAdvance>
     */
    private function reportableAdvances(CashAdvanceReportService $service)
    {
        $candidates = CashAdvance::query()
            ->outstanding()
            ->with(['employee.basicData', 'reports'])
            ->orderBy('request_date')
            ->get();

        $eligible = $service->eligibleAdvanceIds($candidates);

        return $candidates->whereIn('id', $eligible)->values();
    }

    /**
     * CA yang akan dilaporkan, dengan penjagaannya.
     *
     * 🔴 BEDA DARI SISI ESS: kepemilikan TIDAK diperiksa — itulah gunanya slug
     * `.create`, yang memang berarti "boleh membuat atas nama orang lain".
     * Yang tetap diperiksa adalah KELAYAKAN CA-nya, lewat satu-satunya pihak
     * yang berwenang menjawabnya (D154).
     */
    private function resolveAdvance(Request $request, CashAdvanceReportService $service): CashAdvance
    {
        $id = (int) $request->input('ca', $request->query('ca', 0));

        abort_if($id === 0, 404, 'No cash advance was selected for this report.');

        $advance = CashAdvance::with(['employee.basicData', 'reports'])->findOrFail($id);

        $gate = $service->checkEligibility($advance);

        abort_if(! $gate['allowed'], 422, $gate['reason']);

        return $advance;
    }

    /**
     * Boleh mengubah laporan INI?
     *
     * Hak `.manage` DAN laporannya masih terbuka. Laporan yang sudah disetujui
     * tidak dapat diubah: nominalnya sudah menutup buku CA induknya, dan
     * mengubahnya diam-diam akan membuat dua dokumen bercerita berbeda tentang
     * uang yang sama.
     */
    protected function canEditDocument(CashAdvanceReport $report): bool
    {
        // `! trashed()` bukan pengulangan: laporan yang dihapus mempertahankan
        // statusnya, jadi tanpa ini tombol Edit tetap ditawarkan pada dokumen
        // yang sudah tidak ada di daftar.
        return $this->can('general.cash-advance-report.manage')
            && ! $report->trashed()
            && in_array($report->status, CashAdvanceReport::OPEN_STATUSES, true);
    }

    /** Kalimat penolakan yang menyebut SEBABNYA. */
    protected function editRefusalReason(CashAdvanceReport $report): string
    {
        if (! $this->can('general.cash-advance-report.manage')) {
            return 'You do not have permission to edit cash advance reports.';
        }

        return 'Report ' . $report->report_no . ' is already ' . $report->statusLabel()
            . ' and can no longer be edited.';
    }

    /** @return array<string, string> */
    protected function filters(Request $request): array
    {
        return [
            'status'     => (string) $request->query('status', 'open'),
            'scope'      => (string) $request->query('scope', 'all'),
            'month'      => (string) $request->query('month', ''),
            'search'     => trim((string) $request->query('search', '')),
            'settlement' => (string) $request->query('settlement', ''),
        ];
    }

    protected function baseQuery(array $filters)
    {
        return CashAdvanceReport::query()
            ->with(['employee.basicData', 'cashAdvance', 'approvals.actor.basicData', 'approvals.role'])

            ->when($filters['status'] === 'open',
                fn ($q) => $q->whereIn('status', CashAdvanceReport::OPEN_STATUSES))

            ->when(! in_array($filters['status'], ['open', 'all', 'deleted'], true),
                fn ($q) => $q->where('status', $filters['status']))

            ->when($filters['status'] === 'deleted' && $this->canManage(),
                fn ($q) => $q->onlyTrashed())

            // Menyaring pada arah uangnya: siapa yang harus membayar siapa.
            // Inilah gunanya `settlement_type` DISIMPAN dan bukan dihitung saat
            // dibaca — menyaring lewat ekspresi menghalangi indeks.
            ->when(in_array($filters['settlement'], CashAdvanceReport::SETTLEMENT_TYPES, true),
                fn ($q) => $q->where('settlement_type', $filters['settlement']))

            ->when($filters['month'] !== '', function ($q) use ($filters) {
                [$year, $month] = array_pad(explode('-', $filters['month']), 2, null);

                $q->whereYear('report_date', (int) $year)
                  ->whereMonth('report_date', (int) $month);
            })

            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = $filters['search'];

                $q->where(function ($inner) use ($term) {
                    $inner->where('report_no', 'like', "%{$term}%")
                          ->orWhere('description', 'like', "%{$term}%")
                          ->orWhere('notes', 'like', "%{$term}%")
                          // Dicari juga lewat nomor CA induknya: orang menghafal
                          // nomor uang mukanya, bukan nomor laporannya.
                          ->orWhereHas('cashAdvance', fn ($c) => $c->where('request_no', 'like', "%{$term}%"))
                          ->orWhereHas('employee', fn ($e) => $e->where('eci', 'like', "%{$term}%"))
                          ->orWhereHas('employee.basicData', fn ($b) => $b->where('nick_name', 'like', "%{$term}%"));
                });
            });
    }

    protected function canManage(): bool
    {
        return $this->can('general.cash-advance-report.manage');
    }

    /**
     * 🔴 Properti INSTANCE, bukan `static` di dalam method — cacat izin nyata
     * yang ditemukan di P5: `static` di dalam method dibagi oleh SELURUH instance
     * kelas dalam satu proses PHP, sehingga pada proses berumur panjang izin
     * pengguna pertama ikut terbawa ke pengguna berikutnya.
     */
    private ?Employee $actor = null;
    private bool $actorLoaded = false;

    protected function can(string $slug): bool
    {
        if (! $this->actorLoaded) {
            $this->actor       = Employee::where('employee_id', session('user.id'))->first();
            $this->actorLoaded = true;
        }

        return $this->actor?->canAccessMenu($slug) ?? false;
    }
}
