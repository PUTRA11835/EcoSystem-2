<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\BuildsCashAdvanceForms;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Models\Employee;
use App\Services\CashAdvance\CashAdvanceService;
use Illuminate\Http\Request;

/**
 * Pengelolaan uang muka (sisi HR / Finance / penyetuju).
 *
 * Pemisahan izin yang sengaja tidak digabung, meniru tiga sub-modul sebelumnya
 * (Keputusan D77):
 *   general.cash-advance          boleh MEMBUKA halaman dan membaca dokumen
 *   general.cash-advance.approve  boleh bertindak pada langkah yang menunggu DIRINYA
 *   general.cash-advance.manage   boleh MENGUBAH dan MENGHAPUS dokumen
 *   general.cash-advance.export   boleh mengunduh Excel
 *
 * Tanpa pemisahan itu, memberi hak meninjau otomatis memberi hak menghapus
 * dokumen yang menjadi dasar keluarnya uang perusahaan.
 *
 * 🔴 DUA LAPIS IZIN, JANGAN DISATUKAN. Slug menjawab "boleh membuka halaman
 * ini?"; langkah persetujuan menjawab "dokumen ini menunggu siapa?". Tombol
 * Approve/Reject baru dirender bila KEDUANYA terpenuhi — slug lewat `can()`,
 * langkah lewat `CashAdvanceService::canAct()`. Dan keduanya diperiksa ULANG di
 * service saat tombolnya ditekan: tombol yang disembunyikan bukan penjagaan.
 *
 * ── A6 ─────────────────────────────────────────────────────────────────────
 * "New CA" (atas nama karyawan), Edit, hapus beralasan (D109), dan Export
 * Excel — dokumen tunggal maupun rekap bulanan — sudah hidup di sini.
 *
 * 🔴 Form-nya BUKAN berkas baru. `submit.blade.php` yang sama melayani "Submit
 * CA" milik karyawan, "New CA", dan "Edit CA"; yang membedakan hanya variabel
 * yang dikirim. Tiga form yang mirip pasti menyimpang, dan yang menyimpang di
 * sini adalah form dokumen keuangan.
 */
class CashAdvanceController extends Controller
{
    // approverChoice() · nameOf() · employeeOptions() · validasi badan form —
    // dipakai BERSAMA dengan MyCashAdvanceController. Satu aturan untuk
    // "Submit CA", "New CA", dan "Edit CA".
    use BuildsCashAdvanceForms;

    public function index(Request $request, CashAdvanceService $service)
    {
        $filters = $this->filters($request);
        $actorId = (int) session('user.id');

        // Id yang menjadi giliran orang ini. Dihitung SEKALI lalu dipakai untuk
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

        $counts = CashAdvance::query()
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        // 🔴 Uang yang SUDAH KELUAR tetapi bukunya belum ditutup. Inilah angka
        // yang paling berguna bagi bagian keuangan, dan alasan `status` dan
        // `settlement_status` dipisah jadi dua sumbu (D140).
        $outstanding = CashAdvance::query()->outstanding();

        return view('hr-general.cash-advance.index', [
            'requests'  => $requests,
            'filters'   => $filters,
            'mineIds'   => $mineIds,

            // 🔴 Dari service, sekali untuk seluruh halaman (Keputusan D154).
            'reportableIds' => app(\App\Services\CashAdvance\CashAdvanceReportService::class)
                ->eligibleAdvanceIds($requests->getCollection()),

            'settings'  => CashAdvanceSetting::current(),
            'canManage' => $this->canManage(),
            'canApprove'=> $this->can('general.cash-advance.approve'),

            // Empat hak yang berbeda, empat slug yang berbeda (D77) — bukan
            // satu bendera "admin" yang membuka semuanya sekaligus.
            'canCreate' => $this->can('general.cash-advance.create'),
            'canExport' => $this->can('general.cash-advance.export'),
            'counts'    => [
                'pending'   => collect(CashAdvance::OPEN_STATUSES)
                    ->sum(fn ($s) => (int) ($counts[$s]->total ?? 0)),
                'approved'  => (int) ($counts[CashAdvance::STATUS_APPROVED]->total ?? 0),
                'rejected'  => (int) ($counts[CashAdvance::STATUS_REJECTED]->total ?? 0),
                'cancelled' => (int) ($counts[CashAdvance::STATUS_CANCELLED]->total ?? 0),
                'mine'      => count($mineIds),
            ],
            'outstanding' => [
                'count'  => (clone $outstanding)->count(),
                'amount' => (float) (clone $outstanding)->sum('amount'),
            ],
        ]);
    }

    public function show(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $cashAdvance->load([
            'branch', 'project',
            'approvals.actor.basicData', 'approvals.role',
            'employee.basicData',
            'reports',
        ]);

        // 🔴 DUA syarat, dan keduanya wajib: slug `.approve` menjawab "boleh
        // membuka pintu putusan?", `canAct()` menjawab "dokumen ini menunggu
        // dirinya?". Yang kedua saja tidak cukup — penyetuju yang tidak boleh
        // membuka halaman peninjauan tidak pantas diberi tombol putusan.
        $canDecide = $this->can('general.cash-advance.approve')
            && $service->canAct($cashAdvance, (int) session('user.id'), $this->canManage())['allowed'];

        // 🔴 Kelayakan laporan dijawab SERVICE, bukan dihitung dari keadaan model
        // (Keputusan D154). `reports` sudah dimuat di atas, jadi nol kueri baru.
        $reportGate = app(\App\Services\CashAdvance\CashAdvanceReportService::class)
            ->checkEligibility($cashAdvance);

        // 🔴 `show.blade.php` TIDAK dibuat ulang. Ia sudah menerima variabel di
        // bawah sebagai opsional sejak A4; sisi HR tinggal mengisinya. Dua berkas
        // detail yang mirip akan menyimpang cepat atau lambat — dan yang
        // menyimpang di sini adalah tampilan dokumen keuangan.
        return view('hr-general.cash-advance.show', [
            'request'      => $cashAdvance,
            'signatures'   => $service->signatureColumns($cashAdvance),
            'backRoute'    => route('general.cash-advance.index'),
            'printRoute'   => route('general.cash-advance.print', $cashAdvance),
            'canApprove'   => $canDecide,
            'approveRoute' => $canDecide ? route('general.cash-advance.approve', $cashAdvance) : null,
            'rejectRoute'  => $canDecide ? route('general.cash-advance.reject', $cashAdvance) : null,

            // Sisi HR memakai Delete, bukan Cancel: Cancel adalah hak PEMOHON
            // menarik kembali pengajuannya sendiri, Delete adalah tindakan
            // administratif yang menuntut alasan tertulis (D109).
            'cancelRoute'  => null,

            // 🔴 Tombolnya mengikuti hak DAN keadaan dokumen. Keduanya
            // diperiksa ULANG di edit()/update()/destroy() — tombol yang
            // disembunyikan bukan penjagaan.
            'editRoute'    => $this->canEditDocument($cashAdvance, $service)
                ? route('general.cash-advance.edit', $cashAdvance)
                : null,

            // Menghapus tidak menuntut dokumennya terbuka: dokumen yang sudah
            // ditolak atau dibatalkan pun boleh dihapus. Yang menolak justru
            // service, bila CA-nya sudah punya laporan pertanggungjawaban.
            'deleteRoute'  => $this->can('general.cash-advance.manage') && ! $cashAdvance->trashed()
                ? route('general.cash-advance.destroy', $cashAdvance)
                : null,

            'exportRoute'  => $this->can('general.cash-advance.export')
                ? route('general.cash-advance.export.single', $cashAdvance)
                : null,

            // 🔴 Dari SERVICE, bukan dari keadaan model (Keputusan D154). Sisi
            // HR tidak punya tombolnya — `reportRoute` memang null di sini —
            // tetapi keterangan keadaannya harus menjawab pertanyaan yang sama
            // dengan yang dilihat karyawan, kalau tidak dua halaman ini
            // bercerita berbeda tentang dokumen yang sama.
            'canCreateReport'     => $reportGate['allowed'],
            'reportBlockedReason' => $reportGate['allowed'] ? null : $reportGate['reason'],
        ]);
    }

    /**
     * Cetakan sisi HR.
     *
     * 🔴 Memanggil MyCashAdvanceController::renderPrint() — TIDAK menyalin satu
     * baris pun. Dokumen yang dicetak karyawan dan yang dicetak HR harus
     * identik; uji asap A5 membuktikannya **byte-per-byte**, pola yang sama
     * dengan Purchase Request.
     */
    public function print(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        return MyCashAdvanceController::renderPrint($cashAdvance, $service);
    }

    public function approve(Request $request, CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $validated = $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $result = $service->approve(
            $cashAdvance,
            (int) session('user.id'),
            $validated,
            $this->canManage()
        );

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return back()->with('success', $result['completed']
            ? 'Cash advance ' . $cashAdvance->request_no . ' is fully approved.'
            : 'Step approved. ' . $cashAdvance->request_no . ' moved to the next reviewer.');
    }

    public function reject(Request $request, CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        // Alasan WAJIB, minimal 5 huruf — diperiksa di sini DAN di service.
        // Penolakan tanpa alasan membuat pemohon tidak tahu apa yang harus
        // diperbaiki, dan dokumen keuangan yang ditolak tanpa jejak alasan
        // tidak dapat diaudit.
        $validated = $request->validate([
            'notes' => ['required', 'string', 'min:5', 'max:500'],
        ], [
            'notes.required' => 'Please write why this cash advance is rejected.',
            'notes.min'      => 'Please give at least 5 characters of reason.',
        ]);

        $result = $service->reject(
            $cashAdvance,
            (int) session('user.id'),
            $validated['notes'],
            $this->canManage()
        );

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return back()->with('success', 'Cash advance ' . $cashAdvance->request_no . ' was rejected.');
    }

    // ── Membuat atas nama karyawan ("New CA") ───────────────────────────────

    public function create(CashAdvanceService $service)
    {
        $settings = CashAdvanceSetting::current();
        $today    = now()->startOfDay();
        $steps    = $service->activeSteps();

        return view('hr-general.cash-advance.submit', array_merge([
            'mode'        => 'create',
            'document'    => null,
            'employees'   => $this->employeeOptions(),
            'settings'    => $settings,
            'costCenters' => $service->costCenterOptions(),
            'steps'       => $steps,
            'action'      => route('general.cash-advance.store'),
            'backRoute'   => route('general.cash-advance.index'),

            // 🔴 `minDate` null, dan itu BUKAN kelalaian. Pemegang hak buat
            // dianggap admin oleh checkDateRules(), sehingga batas mundur dan
            // periode terkunci memang tidak berlaku baginya — dokumen susulan
            // yang sah adalah alasan hak ini ada. Batas ke DEPAN tetap
            // mengikuti setelan, karena mengeluarkan uang untuk tanggal yang
            // belum tiba bukan hal yang sama.
            'minDate'     => null,
            'maxDate'     => $settings->allow_future_date ? null : $today->toDateString(),

            // Tidak dipakai di cabang ini (dropdown karyawan yang tampil),
            // tetapi form yang sama membacanya pada cabang ESS.
            'requesterName' => $this->nameOf((int) session('user.id')),
        ], $this->approverChoice($steps)));
    }

    public function store(Request $request, CashAdvanceService $service)
    {
        $data = $this->validateCashAdvancePayload($request, true);

        $result = $service->submit(
            (int) $data['employee_id'],
            $data,
            (int) session('user.id')      // pembuatnya tercatat di created_by
        );

        if (! $result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.cash-advance.show', $result['request'])
            ->with('success', 'Cash advance ' . $result['request']->request_no
                . ' created on behalf of the employee.');
    }

    // ── Mengubah dokumen berjalan ───────────────────────────────────────────

    public function edit(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        if (! $this->canEditDocument($cashAdvance, $service)) {
            return redirect()
                ->route('general.cash-advance.show', $cashAdvance)
                ->with('error', $this->editRefusalReason($cashAdvance));
        }

        $cashAdvance->load(['employee.basicData', 'approvals']);

        return view('hr-general.cash-advance.submit', [
            'mode'        => 'edit',
            'document'    => $cashAdvance,
            'employees'   => null,   // pemohon tidak dapat dipindah, lihat form
            'settings'    => CashAdvanceSetting::current(),
            'costCenters' => $service->costCenterOptions(),
            'steps'       => $service->activeSteps(),
            'action'      => route('general.cash-advance.update', $cashAdvance),
            'backRoute'   => route('general.cash-advance.show', $cashAdvance),
            'minDate'     => null,
            'maxDate'     => null,

            'requesterName' => $cashAdvance->employee?->basicData?->nick_name ?? '—',

            // 🔴 Alur dokumen ini sudah dibekukan saat dibuat, dan
            // CashAdvanceService::update() sengaja TIDAK menyusunnya ulang.
            // Menanyakan approver lagi akan menyiratkan penyetujunya masih bisa
            // diganti — padahal sebagian mungkin sudah meninjau.
            'firstStep'          => null,
            'chooseApprover'     => false,
            'approverCandidates' => [],
        ]);
    }

    public function update(Request $request, CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        // 🔴 Diperiksa ULANG di sini, bukan hanya saat merender form. Rute
        // `update` dijaga slug HALAMAN, bukan `.manage`, karena keadaan dokumen
        // tidak dapat dijawab oleh slug — dan permintaan POST dapat disusun
        // tanpa pernah membuka formnya.
        if (! $this->canEditDocument($cashAdvance, $service)) {
            return redirect()
                ->route('general.cash-advance.show', $cashAdvance)
                ->with('error', $this->editRefusalReason($cashAdvance));
        }

        $data   = $this->validateCashAdvancePayload($request, false);
        $result = $service->update($cashAdvance, $data, (int) session('user.id'));

        if (! $result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.cash-advance.show', $cashAdvance)
            ->with('success', 'Cash advance ' . $cashAdvance->request_no . ' updated.');
    }

    /**
     * Hapus — SOFT DELETE beralasan (Keputusan D109).
     *
     * Dokumen keuangan tidak boleh hilang tanpa jejak. Alasannya wajib, dan
     * service menolak menghapus CA yang sudah punya laporan pertanggungjawaban.
     */
    public function destroy(Request $request, CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $validated = $request->validate([
            'delete_reason' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'delete_reason.required' => 'Please state why this document is being deleted.',
            'delete_reason.min'      => 'Please give at least 5 characters of reason.',
        ]);

        $result = $service->softDelete(
            $cashAdvance,
            (int) session('user.id'),
            $validated['delete_reason']
        );

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.cash-advance.index')
            ->with('success', 'Cash advance ' . $cashAdvance->request_no
                . ' deleted. It stays on record and can be found under Status → Deleted.');
    }

    // ── Ekspor ──────────────────────────────────────────────────────────────

    /** Satu dokumen. */
    public function exportSingle(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $cashAdvance->load([
            'branch', 'project', 'approvals.actor.basicData', 'approvals.role',
            'employee.basicData', 'reports',
        ]);

        return $this->download(
            collect([$cashAdvance]),
            $service,
            'Cash Advance ' . $cashAdvance->request_date->format('Y-m'),
            'cash_advance_' . str_replace('/', '-', $cashAdvance->request_no)
        );
    }

    /**
     * Seluruh dokumen pada satu bulan ("Monthly Export").
     *
     * 🔴 Memakai query yang SAMA dengan layar, hanya tanpa paginasi, supaya isi
     * berkas selalu sama dengan yang dilihat pengguna (Keputusan D48). Berkas
     * yang isinya berbeda dari layar adalah cara paling halus untuk membuat
     * orang berhenti memercayai keduanya.
     */
    public function export(Request $request, CashAdvanceService $service)
    {
        $filters = $this->filters($request);

        $rows = $this->baseQuery($filters)
            ->with(['branch', 'project', 'approvals.actor.basicData', 'approvals.role',
                    'employee.basicData', 'reports'])
            ->orderBy('request_date')
            ->orderBy('id')
            ->get();

        $period = $filters['month'] !== '' ? $filters['month'] : now()->format('Y-m');

        return $this->download(
            $rows,
            $service,
            'Cash Advance ' . $period,
            'cash_advance_' . $period
        );
    }

    // ── internal ────────────────────────────────────────────────────────────

    private function download($rows, CashAdvanceService $service, string $sheet, string $file)
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\CashAdvanceDocumentExport($rows, $service, $sheet),
            $file . '.xlsx'
        );
    }

    /**
     * Boleh mengubah dokumen INI?
     *
     * 🔴 SYARAT KEADAAN LEBIH DULU, dan berlaku untuk SIAPA PUN. Dokumen yang
     * sudah disetujui, ditolak, dibatalkan, atau dihapus tidak dapat diubah.
     * Nominal yang sudah dibaca dan ditandatangani penyetuju bukan angka yang
     * boleh berubah diam-diam sesudahnya; kalau memang keliru, dokumennya
     * dihapus beralasan lalu dibuat ulang.
     *
     * Sesudah itu ada DUA jalan sah, dan keduanya memang berbeda:
     *
     *   1. pemegang `general.cash-advance.manage` — kapan pun, selama dokumen
     *      masih terbuka. Ini hak administratif.
     *
     *   2. 🔴 PENYETUJU YANG SEDANG MENDAPAT GILIRAN, bila setelan
     *      `allow_approver_adjust_amount` dinyalakan. Pola yang sama sudah
     *      berjalan di Reimbursement.
     *
     * 🔴 JALAN KEDUA INI SEMPAT TIDAK ADA — dan itu ditemukan audit D52, bukan
     * oleh membaca kode. Sakelarnya tersimpan, checkbox-nya dirender di
     * Settings, komentar rutenya bahkan menjanjikan "ada DUA jalan sah menuju
     * ke sini", tetapi tak satu baris pun membacanya. Sakelar yang tidak
     * berpengaruh adalah kebohongan yang paling sulit ditemukan: tidak ada
     * galat, tidak ada log, tidak ada yang gagal — pengguna menyalakannya,
     * percaya, dan sistemnya berperilaku persis seperti sebelumnya.
     *
     * Haknya HILANG begitu ia menyetujui: `canAct()` hanya benar selama langkah
     * itu masih menunggu dirinya. Penyetuju tidak dapat menyesuaikan nominal
     * setelah meneruskan dokumennya ke orang berikutnya.
     */
    protected function canEditDocument(CashAdvance $request, ?CashAdvanceService $service = null): bool
    {
        // 🔴 `! trashed()` bukan pengulangan dari `isOpen()`. Dokumen yang
        // dihapus MEMPERTAHANKAN statusnya — sebuah CA `submitted` yang dihapus
        // tetap berstatus `submitted`, jadi `isOpen()` sendirian akan
        // menawarkan tombol Edit pada dokumen yang sudah tidak ada di daftar.
        if ($request->trashed() || ! $request->isOpen()) {
            return false;
        }

        if ($this->can('general.cash-advance.manage')) {
            return true;
        }

        if (! CashAdvanceSetting::current()->allow_approver_adjust_amount) {
            return false;
        }

        $service ??= app(CashAdvanceService::class);

        // `canManage: false` disengaja — yang sedang diuji justru jalan NON-admin.
        return $service->canAct($request, (int) session('user.id'), false)['allowed'];
    }

    /** Kalimat penolakan yang menyebut SEBABNYA, bukan sekadar "tidak boleh". */
    protected function editRefusalReason(CashAdvance $request): string
    {
        if ($request->trashed() || ! $request->isOpen()) {
            return 'Cash advance ' . $request->request_no . ' is already '
                . $request->statusLabel() . ' and can no longer be edited.';
        }

        if (! CashAdvanceSetting::current()->allow_approver_adjust_amount) {
            return 'You do not have permission to edit cash advance documents.';
        }

        // Setelannya menyala, tetapi orang ini bukan penyetuju yang ditunggu.
        return 'Only the reviewer whose turn it is may adjust cash advance '
            . $request->request_no . ', and only while it is waiting for them.';
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
        return CashAdvance::query()
            // `reports` ikut dimuat supaya penilaian kelayakan laporan pada
            // halaman daftar (D154) tidak menembakkan satu kueri per baris.
            ->with(['employee.basicData', 'approvals.actor.basicData', 'approvals.role', 'reports'])

            ->when($filters['status'] === 'open',
                fn ($q) => $q->whereIn('status', CashAdvance::OPEN_STATUSES))

            // 🔴 `outstanding` menyaring pada sumbu KEDUA: dokumen yang sudah
            // disetujui tetapi uangnya belum dipertanggungjawabkan. Itu bukan
            // nilai `status`, jadi ia tidak bisa ikut cabang di bawahnya.
            ->when($filters['status'] === 'outstanding', fn ($q) => $q->outstanding())

            // `cancelled` TIDAK digabung ke `rejected`: keduanya beda sebab —
            // yang satu ditarik pemohon sebelum ditinjau, yang lain diputuskan
            // penyetuju. Menggabungkannya membuat rekap tidak dapat menjawab
            // "berapa yang ditolak?".
            ->when(! in_array($filters['status'], ['open', 'all', 'deleted', 'outstanding'], true),
                fn ($q) => $q->where('status', $filters['status']))

            // Dokumen terhapus hanya dapat dilihat pemegang hak kelola
            // (D109) — ia tetap ada demi audit, bukan demi rekap harian.
            ->when($filters['status'] === 'deleted' && $this->canManage(),
                fn ($q) => $q->onlyTrashed())

            ->when($filters['month'] !== '', function ($q) use ($filters) {
                [$year, $month] = array_pad(explode('-', $filters['month']), 2, null);

                $q->whereYear('request_date', (int) $year)
                  ->whereMonth('request_date', (int) $month);
            })

            // Sesuai placeholder acuan: "Document no, requester, notes…"
            ->when($filters['search'] !== '', function ($q) use ($filters) {
                $term = $filters['search'];

                $q->where(function ($inner) use ($term) {
                    $inner->where('request_no', 'like', "%{$term}%")
                          ->orWhere('description', 'like', "%{$term}%")
                          ->orWhere('notes', 'like', "%{$term}%")
                          ->orWhereHas('employee', fn ($e) => $e->where('eci', 'like', "%{$term}%"))
                          ->orWhereHas('employee.basicData', fn ($b) => $b->where('nick_name', 'like', "%{$term}%"));
                });
            });
    }

    /** Apakah pengguna aktif memegang izin mengelola (ubah kapan pun / hapus)? */
    protected function canManage(): bool
    {
        return $this->can('general.cash-advance.manage');
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
     * "berganti pengguna" ternyata tidak mengganti apa pun. Uji asap A5 memakai
     * cara yang sama, jadi cacat itu akan langsung terlihat kalau terulang.
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
        if (! $this->actorLoaded) {
            $this->actor       = Employee::where('employee_id', session('user.id'))->first();
            $this->actorLoaded = true;
        }

        return $this->actor?->canAccessMenu($slug) ?? false;
    }
}
