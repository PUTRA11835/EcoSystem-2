<?php

namespace App\Http\Controllers\HR_General;

use App\Http\Controllers\Controller;
use App\Http\Controllers\HR_General\Concerns\BuildsCashAdvanceForms;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceReport;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Services\CashAdvance\CashAdvanceReportService;
use Illuminate\Http\Request;

/**
 * Pertanggungjawaban uang muka mandiri (ESS) — sisi karyawan.
 *
 * CAR menjawab pertanyaan yang ditinggalkan CA: "uangnya dipakai untuk apa, dan
 * berapa sisanya". Menyetujui langkah terakhirnya ikut MENUTUP BUKU CA induknya
 * — mesinnya di CashAdvanceReportService::syncCashAdvance().
 *
 * 🔴 `employee_id` SELALU dari sesi, tidak pernah dari badan request.
 * 🔴 KEPEMILIKAN diperiksa pada `show`, `print`, dan `cancel` — slug hanya
 * menjawab "boleh membuka halaman ini?", bukan "dokumen SIAPA ini?".
 *
 * 🔴 SATU HAL YANG BERBEDA DARI CA: dokumen ini selalu lahir dari CA tertentu.
 * Karena itu `create` menuntut parameter `?ca=`, dan kelayakan CA-nya diperiksa
 * DUA KALI — saat form dibuka dan saat disimpan. Data dapat berubah di antara
 * keduanya: CA yang tadi layak bisa sudah dilaporkan orang lain lewat "New CAR".
 */
class MyCashAdvanceReportController extends Controller
{
    // approverChoice() · nameOf() · validasi badan form — dipakai BERSAMA
    // dengan CashAdvanceReportController. Satu aturan, bukan dua salinan.
    use BuildsCashAdvanceForms;

    public function index(CashAdvanceReportService $car)
    {
        $employeeId = (int) session('user.id');

        return view('hr-general.cash-advance.report-index', [
            'reports'  => $car->history($employeeId),
            'settings' => CashAdvanceSetting::current(),

            // CA milik sendiri yang BOLEH dilaporkan sekarang. Ditampilkan
            // sebagai daftar "siap dilaporkan" supaya karyawan tidak perlu
            // bolak-balik ke halaman Cash Advance untuk mencarinya.
            //
            // 🔴 Disaring dengan checkEligibility(), BUKAN dengan scope
            // outstanding() (Keputusan D154). outstanding() menjawab "bukunya
            // belum ditutup" — CA yang laporannya masih diproses masuk ke sana,
            // dan dulu panel ini menampilkan tombol "Create CAR" untuknya yang
            // selalu berujung penolakan. `with('reports')` membuat penyaringan
            // ini nol kueri tambahan.
            'reportable' => $this->eligibleAdvances($employeeId, $car),
        ]);
    }

    public function create(Request $request, CashAdvanceReportService $car)
    {
        $advance = $this->resolveAdvance($request, $car);

        $settings = CashAdvanceSetting::current();
        $today    = now()->startOfDay();

        $steps = $car->activeSteps();

        return view('hr-general.cash-advance.report-submit', array_merge([
            'advance'       => $advance,
            'settings'      => $settings,
            'costCenters'   => app(\App\Services\CashAdvance\CashAdvanceService::class)->costCenterOptions(),
            'steps'         => $steps,
            'maxDate'       => $settings->allow_future_date ? null : $today->toDateString(),
            'requesterName' => $this->nameOf((int) session('user.id')),
        ], $this->approverChoice($steps)));
    }

    public function store(Request $request, CashAdvanceReportService $car)
    {
        $advance = $this->resolveAdvance($request, $car);
        $data    = $this->validateCashAdvanceReportPayload($request);

        $result = $car->submit($advance, (int) session('user.id'), $data);

        if (! $result['allowed']) {
            return back()->withInput()->with('error', $result['reason']);
        }

        $report = $result['report'];

        if ($request->boolean('print_after_save')) {
            return redirect()
                ->route('general.my-cash-advance-report.print', $report)
                ->with('success', 'Cash advance report ' . $report->report_no . ' submitted.');
        }

        return redirect()
            ->route('general.my-cash-advance-report.index')
            ->with('success', 'Cash advance report ' . $report->report_no
                . ' submitted and is waiting for review.');
    }

    public function show(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $this->authoriseOwner($cashAdvanceReport);

        $cashAdvanceReport->load([
            'cashAdvance', 'items.branch', 'items.project',
            'approvals.actor.basicData', 'approvals.role',
            'employee.basicData',
        ]);

        $settings = CashAdvanceSetting::current();

        return view('hr-general.cash-advance.report-show', [
            'report'      => $cashAdvanceReport,
            'signatures'  => $service->signatureColumns($cashAdvanceReport),
            'backRoute'   => route('general.my-cash-advance-report.index'),
            'printRoute'  => route('general.my-cash-advance-report.print', $cashAdvanceReport),
            'advanceRoute'=> route('general.my-cash-advance.show', $cashAdvanceReport->cash_advance_id),

            'cancelRoute' => $settings->allow_requester_cancel && $cashAdvanceReport->isCancellable()
                ? route('general.my-cash-advance-report.cancel', $cashAdvanceReport)
                : null,
        ]);
    }

    public function print(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $this->authoriseOwner($cashAdvanceReport);

        return self::renderPrint($cashAdvanceReport, $service);
    }

    public function cancel(CashAdvanceReport $cashAdvanceReport, CashAdvanceReportService $service)
    {
        $this->authoriseOwner($cashAdvanceReport);

        $result = $service->cancel($cashAdvanceReport, (int) session('user.id'));

        if (! $result['allowed']) {
            return back()->with('error', $result['reason']);
        }

        return redirect()
            ->route('general.my-cash-advance-report.index')
            ->with('success', 'Report ' . $cashAdvanceReport->report_no . ' was cancelled.');
    }

    // ── internal ────────────────────────────────────────────────────────────

    /**
     * Halaman cetak, dipisah supaya sisi HR memakainya kembali tanpa menyalin
     * satu baris pun — dan uji asap membuktikan keluarannya identik.
     */
    public static function renderPrint(CashAdvanceReport $report, CashAdvanceReportService $service)
    {
        $report->load([
            'cashAdvance', 'items.branch', 'items.project',
            'approvals.actor.basicData', 'employee.basicData',
        ]);

        return view('hr-general.cash-advance.report-print', [
            'report'     => $report,
            'heading'    => CashAdvanceSetting::current()->company_name,
            'signatures' => $service->signatureColumns($report),
        ]);
    }

    /**
     * CA milik seorang karyawan yang boleh dilaporkan SEKARANG.
     *
     * Kandidatnya diambil lebar (semua yang bukunya belum ditutup), lalu
     * disaring oleh SATU-SATUNYA pihak yang berwenang menjawabnya —
     * CashAdvanceReportService::checkEligibility(). Panel ini hanya boleh
     * memuat CA yang tombolnya benar-benar akan diterima service.
     *
     * @return \Illuminate\Support\Collection<int, CashAdvance>
     */
    private function eligibleAdvances(int $employeeId, CashAdvanceReportService $car)
    {
        $candidates = CashAdvance::where('employee_id', $employeeId)
            ->outstanding()
            ->with('reports')
            ->orderBy('request_date')
            ->get();

        $eligible = $car->eligibleAdvanceIds($candidates);

        return $candidates->whereIn('id', $eligible)->values();
    }

    /**
     * CA yang akan dilaporkan, beserta seluruh penjagaannya.
     *
     * 🔴 TIGA pemeriksaan, dan ketiganya perlu:
     *   1. parameter `ca` ada — tanpa induk, CAR tidak punya arti
     *   2. CA-nya MILIK orang ini — kalau tidak, siapa pun dapat melaporkan
     *      uang muka rekannya hanya dengan menebak id
     *   3. CA-nya LAYAK dilaporkan — sudah `approved`, belum `settled`, dan
     *      belum punya laporan lain bila `car_multiple_per_ca` mati
     */
    private function resolveAdvance(Request $request, CashAdvanceReportService $car): CashAdvance
    {
        $id = (int) $request->input('ca', $request->query('ca', 0));

        abort_if($id === 0, 404, 'A cash advance report must be created from a cash advance.');

        $advance = CashAdvance::findOrFail($id);

        abort_if((int) $advance->employee_id !== (int) session('user.id'), 403,
            'This cash advance belongs to another employee.');

        $gate = $car->checkEligibility($advance);

        abort_if(! $gate['allowed'], 422, $gate['reason']);

        return $advance;
    }

    private function authoriseOwner(CashAdvanceReport $report): void
    {
        abort_if((int) $report->employee_id !== (int) session('user.id'), 403,
            'This cash advance report belongs to another employee.');
    }

    /**
     * Dropdown Approver untuk alur CAR — mekanisme yang sama dengan CA (D126).
     *
     * @return array{firstStep: ?object, chooseApprover: bool, approverCandidates: array}
     */
}
