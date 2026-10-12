<?php

namespace App\Http\Controllers\Finance;

use App\Exports\PayrollSheetExport;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PayrollSetting;
use App\Services\Payroll\PayrollPeriodService;
use App\Services\Payroll\PayslipPdf;
use App\Support\Payroll\PayrollPeriodRules;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Finance → Payroll → Periods (alur ESH): daftar periode, detail per karyawan, hitung / hitung ulang (periode & per karyawan),
 * Kunci, Generate Slip, slip PDF (Indonesia/Inggris), ekspor Excel & transfer bank.
 *
 * Izin di rute (`routes/finance.php`): lihat = `menu:finance.payroll.periods`; buat = `…,create`; hitung = `…,edit`;
 * hapus = `…,delete`; Kunci = `finance.payroll.lock`; Generate Slip = `finance.payroll.slip` (slug fungsi terpisah).
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
            'summary' => ['total' => $rows->count(), 'open' => $count('open'), 'locked' => $count('locked')],
            'enabled' => $this->periods->isEnabled(),
            'caps' => $this->caps(),
            'defaults' => $this->defaultPeriod(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $id = null;
        $errors = $this->periods->create($request->only(['name', 'period_start', 'period_end', 'pay_date', 'attendance_start', 'attendance_end', 'cutoff_date', 'notes']), $this->actor(), $id);

        return $errors
            ? redirect()->route('finance.payroll.periods')->withErrors($errors)->withInput()
            : redirect()->route('finance.payroll.periods.show', $id)->with('success', 'Payroll period created. Add corrections if needed, then calculate.');
    }

    public function show(int $period): View|RedirectResponse
    {
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        if (!$p) {
            return redirect()->route('finance.payroll.periods')->with('error', 'Payroll period not found.');
        }

        $slips = DB::table('payroll_slips')->where('period_id', $period)->orderBy('employee_name')->get()->map(function ($s) {
            $s->bd = json_decode((string) $s->breakdown, true) ?: [];

            return $s;
        });
        $corrections = DB::table('payroll_adjustments as a')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'a.employee_id')
            ->leftJoin('payroll_periods as sp', 'sp.id', '=', 'a.source_period_id')
            ->where('a.period_id', $period)->orderBy('a.id')
            ->get(['a.*', 'b.first_name', 'b.last_name', 'sp.name as source_name']);

        $editable = PayrollPeriodRules::isEditable($p->status);
        $employees = $editable
            ? DB::table('employee as e')->join('employee_hr_profile as h', 'h.employee_id', '=', 'e.employee_id')
                ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
                ->where('h.payroll_activated', 1)->where('e.is_active', 1)->orderBy('b.first_name')
                ->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name'])
            : collect();

        $me = Employee::find(session('user.id'));
        $settings = PayrollSetting::current();

        return view('finance.payroll.show', [
            'p' => $p, 'slips' => $slips, 'corrections' => $corrections, 'employees' => $employees,
            'otherPeriods' => DB::table('payroll_periods')->where('id', '!=', $period)->orderByDesc('period_start')->get(['id', 'name']),
            'warnings' => json_decode((string) $p->warnings, true) ?: [],
            'enabled' => $this->periods->isEnabled(), 'caps' => $this->caps(), 'editable' => $editable,
            'canLock' => (bool) $me?->canAccessMenu('finance.payroll.lock'),
            'canSlip' => (bool) $me?->canAccessMenu('finance.payroll.slip'),
            'pphOn' => (bool) $settings->pph21_enabled, 'bpjsOn' => (bool) $settings->bpjs_enabled,
            'slipLang' => $settings->slip_language === 'en' ? 'en' : 'id',
            // Gaji pokok per slip (kolom "Base salary" di tabel).
            'baseBySlip' => $slips->isEmpty() ? collect() : DB::table('payroll_slip_items')->whereIn('slip_id', $slips->pluck('id'))->where('code', 'base')
                ->groupBy('slip_id')->selectRaw('slip_id, SUM(amount) as amt')->pluck('amt', 'slip_id'),
            // Saklar efektif per karyawan: penimpaan periode ini, bila tidak ada → data master.
            'toggles' => $this->toggles($period, $slips),
        ]);
    }

    public function calculate(int $period): RedirectResponse
    {
        $summary = null;
        $errors = $this->periods->calculate($period, $this->actor(), $summary);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success',
                "Payroll calculated for {$summary['employees']} employee(s)" . ($summary['skipped'] ? ", {$summary['skipped']} skipped" : '') . '. Review the warnings, then lock the period and generate payslips.');
    }

    public function lock(int $period): RedirectResponse
    {
        $errors = $this->periods->lock($period, $this->actor());

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', 'Payroll locked. It can no longer be changed.');
    }

    public function generateSlips(int $period): RedirectResponse
    {
        $count = 0;
        $errors = $this->periods->generateSlips($period, $this->actor(), $count);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', $count > 0
                ? "{$count} payslip(s) generated with a payslip number and verification code."
                : 'All payslips of this period were already generated.');
    }

    /** "Terapkan & Hitung Ulang" / "Hitung Ulang" untuk satu karyawan. */
    public function recalculateEmployee(Request $request, int $period, int $employee): RedirectResponse
    {
        $toggles = $request->boolean('apply_toggles') ? [
            'bpjs_health' => $request->boolean('bpjs_health'), 'bpjs_employment' => $request->boolean('bpjs_employment'), 'pph21' => $request->boolean('pph21'),
        ] : null;
        $name = null;
        $errors = $this->periods->recalculateEmployee($period, $employee, $toggles, $this->actor(), $name);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', "Payroll recalculated for {$name}.");
    }

    public function destroy(int $period): RedirectResponse
    {
        $errors = $this->periods->delete($period);

        return $errors
            ? redirect()->route('finance.payroll.periods')->withErrors($errors)
            : redirect()->route('finance.payroll.periods')->with('success', 'Payroll period deleted.');
    }

    public function addAdjustment(Request $request, int $period): RedirectResponse
    {
        $errors = $this->periods->addAdjustment($period, $request->only(['employee_id', 'category', 'source_period_id', 'component', 'kind', 'name', 'amount', 'taxable', 'notes']), $this->actor());

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)->withInput()
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', 'Correction added. Recalculate to apply it.');
    }

    public function deleteAdjustment(int $period, int $adjustment): RedirectResponse
    {
        $errors = $this->periods->deleteAdjustment($period, $adjustment);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', 'Correction removed. Recalculate to apply it.');
    }

    public function slip(int $period, int $slip): View|RedirectResponse
    {
        [$p, $s, $items] = $this->loadSlip($period, $slip);
        if (!$s) {
            return redirect()->route('finance.payroll.periods.show', $period)->with('error', 'Payslip not found.');
        }

        return view('finance.payroll.slip', ['p' => $p, 's' => $s, 'items' => $items, 'breakdown' => json_decode((string) $s->breakdown, true) ?: []]);
    }

    /** Slip PDF format ESH, bahasa Indonesia (id) atau Inggris (en): ?lang=… menimpa bawaan di Payroll Settings. */
    public function slipPdf(Request $request, int $period, int $slip)
    {
        $pdf = PayslipPdf::make($period, $slip, $request->query('lang'));
        abort_unless($pdf, 404);
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        $s = DB::table('payroll_slips')->where('id', $slip)->first();

        return $pdf->stream(PayslipPdf::fileName($p, $s));
    }
    /** Rincian slip untuk jendela "Detail": ringkasan, item, dasar BPJS & PPh 21, kehadiran. */
    public function slipDetail(int $period, int $slip): JsonResponse
    {
        [$p, $s, $items] = $this->loadSlip($period, $slip);
        abort_unless($s, 404);
        $bd = json_decode((string) $s->breakdown, true) ?: [];

        return response()->json([
            'employee' => ['name' => $s->employee_name, 'eci' => $s->employee_eci, 'position' => $s->position, 'department' => $s->department, 'ptkp' => $s->ptkp_code],
            'period' => ['name' => $p->name, 'start' => $p->period_start, 'end' => $p->period_end],
            'slip_no' => $s->slip_no,
            'items' => $items->map(fn ($i) => ['type' => $i->type, 'code' => $i->code, 'name' => $i->name, 'amount' => (float) $i->amount])->values(),
            'summary' => $bd['summary'] ?? [], 'bpjs' => $bd['bpjs'] ?? null, 'pph21' => $bd['pph21'] ?? null,
            'attendance' => $bd['attendance'] ?? null, 'warnings' => $bd['warnings'] ?? [], 'overtime_hours' => $bd['overtime_hours'] ?? 0,
        ]);
    }

    /** Ekspor Excel tabel periode (format ESH): satu baris per karyawan. */
    public function exportExcel(int $period)
    {
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        abort_unless($p, 404);
        $slips = DB::table('payroll_slips')->where('period_id', $period)->orderBy('employee_name')->get();
        $base = DB::table('payroll_slip_items')->whereIn('slip_id', $slips->pluck('id'))->where('code', 'base')->groupBy('slip_id')->selectRaw('slip_id, SUM(amount) a')->pluck('a', 'slip_id');
        $rows = $slips->map(function ($s) use ($base) {
            $sum = (json_decode((string) $s->breakdown, true) ?: [])['summary'] ?? [];
            $direct = round((float) ($sum['absence_deduction'] ?? 0) + (float) ($sum['late_penalty'] ?? 0) + (float) $s->component_deductions, 2);

            return [$s->employee_eci, $s->employee_name, $s->department, $s->position, (float) ($base[$s->id] ?? 0), (float) $s->gross_earnings,
                $direct, (float) $s->pph21, (float) $s->take_home_pay, $s->published_at ? 'Published' : ($s->slip_no ? 'Slip generated' : 'Calculated')];
        })->all();

        return Excel::download(new PayrollSheetExport(
            ['Employee ID', 'Name', 'Department', 'Position', 'Base salary', 'Earnings', 'Deductions', 'PPh 21', 'Total take-home pay', 'Status'],
            $rows, ['E', 'F', 'G', 'H', 'I'], ['A'],
        ), 'payroll_' . date('Ym', strtotime($p->period_end)) . '.xlsx');
    }

    /** Transfer bank (Excel, format ESH): hanya karyawan dengan gaji bersih positif — bank tidak bisa mentransfer nol/negatif. */
    public function export(int $period)
    {
        $p = DB::table('payroll_periods')->where('id', $period)->first();
        abort_unless($p, 404);
        $slips = DB::table('payroll_slips')->where('period_id', $period)->where('take_home_pay', '>', 0)->orderBy('employee_name')->get();
        $holders = DB::table('employee_bank')->whereIn('employee_id', $slips->pluck('employee_id'))->orderByDesc('valid_from')->get(['employee_id', 'account_number', 'account_holder'])
            ->groupBy('employee_id');
        $rows = $slips->map(function ($s) use ($holders) {
            $h = ($holders[$s->employee_id] ?? collect())->firstWhere('account_number', $s->bank_account) ?? ($holders[$s->employee_id] ?? collect())->first();

            return [$s->employee_eci, $s->employee_name, $s->bank_name, $s->bank_account, $h->account_holder ?? '', (float) $s->take_home_pay];
        })->all();

        return Excel::download(new PayrollSheetExport(
            ['Employee ID', 'Name', 'Bank', 'Account No.', 'Account holder', 'Transfer amount'], $rows, ['F'], ['A', 'D'],
        ), 'bank_transfer_' . date('Ym', strtotime($p->period_end)) . '.xlsx');
    }

    public function publish(int $period): RedirectResponse
    {
        $count = 0;
        $errors = $this->periods->publish($period, $this->actor(), $count);

        return $errors
            ? redirect()->route('finance.payroll.periods.show', $period)->withErrors($errors)
            : redirect()->route('finance.payroll.periods.show', $period)->with('success', $count > 0
                ? "{$count} payslip(s) published. Employees can now see them in ESS → Paystub."
                : 'All generated payslips were already published.');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** @return array<int,array{bpjs_health:bool,bpjs_employment:bool,pph21:bool}> */
    private function toggles(int $period, $slips): array
    {
        $ids = $slips->pluck('employee_id')->all();
        $master = DB::table('employee_hr_profile')->whereIn('employee_id', $ids)->get(['employee_id', 'bpjs_health_active', 'bpjs_employment_active'])->keyBy('employee_id');
        $ov = DB::table('payroll_employee_overrides')->where('period_id', $period)->get()->keyBy('employee_id');
        $out = [];
        foreach ($ids as $id) {
            $m = $master->get($id);
            $o = $ov->get($id);
            $out[$id] = [
                'bpjs_health' => (bool) ($o && $o->bpjs_health !== null ? $o->bpjs_health : ($m->bpjs_health_active ?? false)),
                'bpjs_employment' => (bool) ($o && $o->bpjs_employment !== null ? $o->bpjs_employment : ($m->bpjs_employment_active ?? false)),
                'pph21' => (bool) ($o && $o->pph21 !== null ? $o->pph21 : true),
            ];
        }

        return $out;
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

    /** Isian awal formulir: bulan berjalan. @return array<string,string> */
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
