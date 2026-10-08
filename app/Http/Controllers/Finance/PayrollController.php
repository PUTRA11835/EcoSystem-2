<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Services\Payroll\PayrollPeriodService;
use App\Support\Payroll\PayrollPeriodRules;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Finance → Payroll → Periods: daftar periode, detail, hitung/hitung ulang, setujui, dibayar, kunci, slip, ekspor.
 *
 * Izin di rute (`routes/finance.php`): lihat = `menu:finance.payroll.periods`; buat = `…,create`; hitung = `…,edit`;
 * hapus = `…,delete`; setujui/buka kembali, dibayar, kunci = slug fungsi terpisah (pemisahan tugas).
 * Hanya GET/POST. Semua aksi tulis lewat PayrollPeriodService (saklar Payroll di Payroll → Settings + aturan status).
 */
class PayrollController extends Controller
{
    public function __construct(private readonly PayrollPeriodService $periods)
    {
    }

    public function home(): RedirectResponse
    {
        $me = Employee::find(session('user.id'));
        foreach (['periods' => 'finance.payroll.periods', 'simulation' => 'finance.payroll.simulation', 'settings' => 'finance.payroll.settings'] as $route => $slug) {
            if ($me?->canAccessMenu($slug)) {
                return redirect()->route('finance.payroll.' . $route);
            }
        }

        return redirect()->route('dashboard');
    }

    public function index(): View
    {
        $rows = DB::table('payroll_periods')->orderByDesc('period_start')->get();
        $count = fn (string $s) => $rows->where('status', $s)->count();

        return view('finance.payroll.index', [
            'periods' => $rows,
            'summary' => [
                'total' => $rows->count(), 'open' => $count('open'),
                'approved' => $count('approved') + $count('paid'), 'locked' => $count('locked'),
            ],
            'enabled' => $this->periods->isEnabled(),
            'caps' => $this->caps(),
            'defaults' => $this->defaultPeriod(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $id = null;
        $errors = $this->periods->create($request->only(['name', 'period_start', 'period_end', 'pay_date', 'notes']), $this->actor(), $id);

        return $errors
            ? redirect()->route('finance.payroll.periods')->withErrors($errors)->withInput()
            : redirect()->route('finance.payroll.periods.show', $id)->with('success', 'Payroll period created. Add adjustments if needed, then calculate.');
    }

    public function show(int $period): View|RedirectResponse
    {
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        if (!$p) {
            return redirect()->route('finance.payroll.periods')->with('error', 'Payroll period not found.');
        }

        $slips = DB::table('payroll_slips')->where('period_id', $period)->orderBy('employee_name')->get();
        $adjustments = DB::table('payroll_adjustments as a')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'a.employee_id')
            ->where('a.period_id', $period)->orderBy('a.id')
            ->get(['a.*', 'b.first_name', 'b.last_name']);

        $employees = PayrollPeriodRules::isEditable($p->status)
            ? DB::table('employee as e')->join('employee_hr_profile as h', 'h.employee_id', '=', 'e.employee_id')
                ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
                ->where('h.payroll_activated', 1)->where('e.is_active', 1)->orderBy('b.first_name')
                ->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name'])
            : collect();

        return view('finance.payroll.show', [
            'p' => $p, 'slips' => $slips, 'adjustments' => $adjustments, 'employees' => $employees,
            'warnings' => json_decode((string) $p->warnings, true) ?: [],
            'enabled' => $this->periods->isEnabled(), 'caps' => $this->caps(),
            'editable' => PayrollPeriodRules::isEditable($p->status),
        ]);
    }

    public function calculate(int $period): RedirectResponse
    {
        $summary = null;
        $errors = $this->periods->calculate($period, $this->actor(), $summary);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success',
                "Payroll calculated for {$summary['employees']} employee(s)" . ($summary['skipped'] ? ", {$summary['skipped']} skipped" : '') . '. Review the warnings, then approve.');
    }

    public function approve(int $period): RedirectResponse { return $this->move($period, 'approve', 'Payroll approved.'); }
    public function reopen(int $period): RedirectResponse { return $this->move($period, 'reopen', 'Payroll reopened for changes.'); }
    public function pay(int $period): RedirectResponse { return $this->move($period, 'pay', 'Payroll marked as paid.'); }
    public function lock(int $period): RedirectResponse { return $this->move($period, 'lock', 'Payroll locked. It can no longer be changed.'); }

    public function destroy(int $period): RedirectResponse
    {
        $errors = $this->periods->delete($period);

        return $errors
            ? redirect()->route('finance.payroll.periods')->withErrors($errors)
            : redirect()->route('finance.payroll.periods')->with('success', 'Payroll period deleted.');
    }

    public function addAdjustment(Request $request, int $period): RedirectResponse
    {
        $errors = $this->periods->addAdjustment($period, $request->only(['employee_id', 'kind', 'name', 'amount', 'taxable', 'notes']), $this->actor());

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)->withInput()
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', 'Adjustment added. Recalculate to apply it.');
    }

    public function deleteAdjustment(int $period, int $adjustment): RedirectResponse
    {
        $errors = $this->periods->deleteAdjustment($period, $adjustment);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', 'Adjustment removed. Recalculate to apply it.');
    }

    public function slip(int $period, int $slip): View|RedirectResponse
    {
        [$p, $s, $items] = $this->loadSlip($period, $slip);
        if (!$s) {
            return redirect()->route('finance.payroll.periods.show', $period)->with('error', 'Payslip not found.');
        }

        return view('finance.payroll.slip', ['p' => $p, 's' => $s, 'items' => $items, 'breakdown' => json_decode((string) $s->breakdown, true) ?: []]);
    }

    public function slipPdf(int $period, int $slip)
    {
        [$p, $s, $items] = $this->loadSlip($period, $slip);
        abort_unless($s, 404);

        return Pdf::loadView('finance.payroll.slip-pdf', ['p' => $p, 's' => $s, 'items' => $items])
            ->setPaper('a4')
            ->stream('payslip-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $s->employee_name) . '-' . $p->period_end . '.pdf');
    }

    /** Laporan transfer bank (CSV): karyawan, bank, rekening, gaji bersih. Sel diamankan dari injeksi rumus. */
    public function export(int $period): StreamedResponse
    {
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        abort_unless($p, 404);
        $slips = DB::table('payroll_slips')->where('period_id', $period)->orderBy('employee_name')->get();
        $safe = fn ($v) => is_string($v) && $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'" . $v : $v;

        return response()->streamDownload(function () use ($slips, $safe) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");   // BOM agar Excel membaca UTF-8
            fputcsv($out, ['Employee', 'ECI', 'Bank', 'Account number', 'Gross', 'Deductions', 'Take-home pay']);
            foreach ($slips as $s) {
                fputcsv($out, [$safe($s->employee_name), $safe((string) $s->employee_eci), $safe((string) $s->bank_name), $safe((string) $s->bank_account),
                    number_format((float) $s->gross_earnings, 2, '.', ''), number_format((float) $s->total_deductions, 2, '.', ''), number_format((float) $s->take_home_pay, 2, '.', '')]);
            }
            fclose($out);
        }, 'payroll-' . $p->period_start . '_' . $p->period_end . '.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    private function move(int $period, string $action, string $ok): RedirectResponse
    {
        $errors = $this->periods->transition($period, $action, $this->actor());

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', $ok);
    }

    /** @return array{0:object|null,1:object|null,2:\Illuminate\Support\Collection} */
    private function loadSlip(int $period, int $slip): array
    {
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        $s = DB::table('payroll_slips')->where('period_id', $period)->where('id', $slip)->first();
        $items = $s ? DB::table('payroll_slip_items')->where('slip_id', $slip)->orderBy('sort')->get() : collect();

        return [$p, $s, $items];
    }

    /** @return array{create:bool,edit:bool,delete:bool} */
    private function caps(): array
    {
        $me = Employee::find(session('user.id'));

        return [
            'create' => (bool) $me?->hasMenuPermission('finance.payroll.periods', 'can_create'),
            'edit'   => (bool) $me?->hasMenuPermission('finance.payroll.periods', 'can_edit'),
            'delete' => (bool) $me?->hasMenuPermission('finance.payroll.periods', 'can_delete'),
        ];
    }

    /** Isian awal formulir: bulan berjalan. @return array{name:string,start:string,end:string,pay:string} */
    private function defaultPeriod(): array
    {
        $d = now();

        return [
            'name' => 'Payroll ' . $d->translatedFormat('F Y'),
            'start' => $d->copy()->startOfMonth()->toDateString(),
            'end' => $d->copy()->endOfMonth()->toDateString(),
            'pay' => $d->copy()->endOfMonth()->toDateString(),
        ];
    }

    private function actor(): ?int
    {
        $id = session('user.id');

        return $id ? (int) $id : null;
    }
}
