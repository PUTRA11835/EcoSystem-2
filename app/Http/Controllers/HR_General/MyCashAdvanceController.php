<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\BuildsCashAdvanceForms;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Services\CashAdvance\CashAdvanceService;
use Illuminate\Http\Request;

/**
 * Pengajuan uang muka mandiri (ESS) — sisi karyawan.
 *
 * 🔴 `employee_id` SELALU diambil dari sesi, tidak pernah dari badan request.
 * Menyisipkan employee_id orang lain lewat DevTools tidak berpengaruh apa pun.
 *
 * 🔴 KEPEMILIKAN diperiksa pada `show`, `print`, dan `cancel` — bukan hanya lewat
 * slug menu. Slug hanya menjawab "boleh membuka halaman ini?"; ia tidak tahu
 * dokumen SIAPA yang sedang dibuka. Tanpa pemeriksaan ini, siapa pun yang boleh
 * mengajukan dapat membaca — atau membatalkan — dokumen rekannya hanya dengan
 * menebak id-nya. Di modul yang mengeluarkan uang perusahaan, itu bukan
 * kebocoran yang murah.
 */
class MyCashAdvanceController extends Controller
{
    // approverChoice() · nameOf() · employeeOptions() · validasi badan form —
    // dipakai BERSAMA dengan CashAdvanceController. Satu aturan, bukan dua
    // salinan yang akan menyimpang.
    use BuildsCashAdvanceForms;

    public function index(CashAdvanceService $ca)
    {
        $employeeId = (int) session('user.id');
        $now        = now();

        // history() sudah ->with('reports'), jadi penyaringan kelayakan di bawah
        // tidak menambah satu kueri pun.
        $requests = $ca->history($employeeId);

        return view('hr-general.cash-advance.my-cash-advance', [
            'requests' => $requests,

            // 🔴 SATU keputusan, dihitung SEKALI, dipakai untuk menandai baris
            // (Keputusan D154). Layar tidak pernah menghitung sendiri apakah
            // sebuah CA boleh dilaporkan — service yang menjawabnya.
            'reportableIds' => app(\App\Services\CashAdvance\CashAdvanceReportService::class)
                ->eligibleAdvanceIds($requests),

            'summary'  => $ca->monthlySummary($employeeId, (int) $now->format('Y'), (int) $now->format('n')),
            'settings' => CashAdvanceSetting::current(),
            'month'    => $now->format('F Y'),
        ]);
    }

    public function create(CashAdvanceService $ca)
    {
        $settings = CashAdvanceSetting::current();

        // Batas tanggal dihitung di sini, bukan di Blade, supaya atribut min/max
        // pada input tanggal selalu sejalan dengan aturan yang ditegakkan service
        // — pengguna tidak menemui penolakan setelah menekan kirim.
        $today = now()->startOfDay();
        $steps = $ca->activeSteps();

        return view('hr-general.cash-advance.submit', array_merge([
            'settings'      => $settings,
            'costCenters'   => $ca->costCenterOptions(),
            'steps'         => $steps,
            'minDate'       => $settings->hasBackdateLimit()
                ? $today->copy()->subDays($settings->max_backdate_days)->toDateString()
                : null,
            'maxDate'       => $settings->allow_future_date ? null : $today->toDateString(),
            'requesterName' => $this->nameOf((int) session('user.id')),
        ], $this->approverChoice($steps)));
    }

    public function store(Request $request, CashAdvanceService $ca)
    {
        $employeeId = (int) session('user.id');
        $data       = $this->validateCashAdvancePayload($request);

        $result = $ca->submit($employeeId, $data);

        if (! $result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        $document = $result['request'];

        // Dialog "PRINT DOCUMENT?" pada aplikasi acuan menawarkan simpan+cetak.
        // Pilihannya dikirim sebagai field biasa, bukan ditangani JavaScript
        // sesudah simpan: nomor dokumennya baru ada SETELAH tersimpan, jadi
        // halaman cetaknya memang hanya dapat dituju dari sini.
        if ($request->boolean('print_after_save')) {
            return redirect()
                ->route('general.my-cash-advance.print', $document)
                ->with('success', 'Cash advance ' . $document->request_no . ' submitted.');
        }

        return redirect()
            ->route('general.my-cash-advance.index')
            ->with('success', 'Cash advance ' . $document->request_no
                . ' submitted and is waiting for review.');
    }

    public function show(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $this->authoriseOwner($cashAdvance);

        $cashAdvance->load([
            'branch', 'project',
            'approvals.actor.basicData', 'approvals.role',
            'employee.basicData',
            'reports',
        ]);

        $settings = CashAdvanceSetting::current();

        // `reports` sudah dimuat di atas, jadi gerbang ini tidak menambah kueri.
        $reportGate = app(\App\Services\CashAdvance\CashAdvanceReportService::class)
            ->checkEligibility($cashAdvance, $settings);

        return view('hr-general.cash-advance.show', [
            'request'    => $cashAdvance,
            'signatures' => $service->signatureColumns($cashAdvance),
            'backRoute'  => route('general.my-cash-advance.index'),
            'printRoute' => route('general.my-cash-advance.print', $cashAdvance),

            // Tombol Cancel hanya milik sisi karyawan; sisi HR memakai Delete.
            // DUA gerbang: setelan menyalakannya, keadaan dokumen mengizinkannya.
            'cancelRoute' => $settings->allow_requester_cancel && $cashAdvance->isCancellable()
                ? route('general.my-cash-advance.cancel', $cashAdvance)
                : null,

            // 🔴 Pertanggungjawaban baru boleh dibuat setelah CA disetujui
            // (jawaban C7) — berbeda dari aplikasi acuan, yang menyalakan tombol
            // "Create CAR" sejak dokumen masih `Submitted`.
            //
            // 🔴 Jawabannya diminta ke SERVICE, bukan dihitung sendiri dari
            // keadaan model (Keputusan D154). Hanya service yang tahu setelan
            // `car_multiple_per_ca` dan laporan yang sudah berjalan; menghitung
            // sendiri di sini pernah memunculkan tombol yang selalu ditolak.
            'canCreateReport' => $reportGate['allowed'],

            // 🔴 Hanya PEMILIK dokumen yang mendapat tombolnya. Kelayakan CA-nya
            // diperiksa LAGI di MyCashAdvanceReportController saat form dibuka —
            // data dapat berubah di antara dua halaman.
            'reportRoute' => $reportGate['allowed']
                ? route('general.my-cash-advance-report.create', ['ca' => $cashAdvance->id])
                : null,

            // Alasan penolakan ikut dikirim: kalau tombolnya tidak ada, pemilik
            // dokumen berhak tahu mengapa — bukan sekadar menemukan ruang kosong.
            'reportBlockedReason' => $reportGate['allowed'] ? null : $reportGate['reason'],
        ]);
    }

    public function print(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $this->authoriseOwner($cashAdvance);

        return self::renderPrint($cashAdvance, $service);
    }

    /**
     * Tarik kembali dokumen sendiri (Keputusan D131).
     *
     * Seluruh syaratnya diperiksa ULANG di service — tombol yang disembunyikan
     * di layar bukan penjagaan, dan permintaan POST dapat disusun tanpa membuka
     * halamannya sama sekali.
     */
    public function cancel(CashAdvance $cashAdvance, CashAdvanceService $service)
    {
        $this->authoriseOwner($cashAdvance);

        $result = $service->cancel($cashAdvance, (int) session('user.id'));

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.my-cash-advance.index')
            ->with('success', 'Cash advance ' . $cashAdvance->request_no . ' was cancelled.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Halaman cetak. Dipisah supaya sisi HR (langkah A5) dapat memakainya kembali
     * tanpa menyalin satu baris pun — dokumen yang dicetak karyawan dan yang
     * dicetak HR harus identik. Di Purchase Request kesamaannya dibuktikan
     * byte-per-byte, dan uji yang sama dipakai di sini.
     */
    public static function renderPrint(CashAdvance $request, CashAdvanceService $service)
    {
        $request->load(['branch', 'project', 'approvals.actor.basicData', 'employee.basicData']);

        return view('hr-general.cash-advance.print', [
            'request'    => $request,
            'heading'    => $service->documentHeading($request),
            'signatures' => $service->signatureColumns($request),
        ]);
    }

    private function authoriseOwner(CashAdvance $request): void
    {
        abort_if((int) $request->employee_id !== (int) session('user.id'), 403,
            'This cash advance belongs to another employee.');
    }

}
