<?php

namespace App\Http\Controllers\Finance;

use App\Exports\FinanceTableExport;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Laporan per periode payroll: BPJS dan PPh 21. Hanya MEMBACA slip yang sudah dihitung (payroll_slips) —
 * angkanya selalu sama dengan slip karena diambil dari snapshot slip, bukan dihitung ulang.
 * Izin: `menu:finance.bpjs.report` / `menu:finance.pph21.report` di rute.
 */
class FinanceReportController extends Controller
{
    public function bpjs(Request $request): View
    {
        [$periods, $period] = $this->pickPeriod($request);
        $rows = $period ? $this->bpjsRows($period->id) : [];

        return view('finance.bpjs.report', [
            'periods' => $periods, 'period' => $period, 'rows' => $rows,
            'totals' => [
                'employees' => count($rows),
                'base' => array_sum(array_column($rows, 'base')),
                'employee' => array_sum(array_column($rows, 'employee_total')),
                'employer' => array_sum(array_column($rows, 'employer_total')),
            ],
        ]);
    }

    public function bpjsExport(Request $request): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        [, $period] = $this->pickPeriod($request);
        if (!$period) {
            return redirect()->route('finance.bpjs.report')->with('error', 'Choose a payroll period first.');
        }
        $rows = array_map(fn ($r) => [
            $r['name'], $r['eci'], $r['bpjs_health_no'], $r['bpjs_employment_no'], $r['base'],
            $r['ee']['health'], $r['ee']['jht'], $r['ee']['jp'], $r['employee_total'],
            $r['er']['health'], $r['er']['jht'], $r['er']['jp'], $r['er']['jkk'], $r['er']['jkm'], $r['employer_total'],
        ], $this->bpjsRows($period->id));

        return Excel::download(new FinanceTableExport(
            ['Employee', 'Employee no.', 'BPJS Health no.', 'BPJS Employment no.', 'BPJS base',
             'Employee Health', 'Employee JHT', 'Employee JP', 'Employee total',
             'Employer Health', 'Employer JHT', 'Employer JP', 'Employer JKK', 'Employer JKM', 'Employer total'], $rows
        ), 'bpjs-report-' . $period->period_start . '_' . $period->period_end . '.xlsx');
    }

    public function pph21(Request $request): View
    {
        [$periods, $period] = $this->pickPeriod($request);
        $rows = $period ? $this->pphRows($period->id) : [];

        return view('finance.pph21.report', [
            'periods' => $periods, 'period' => $period, 'rows' => $rows,
            'totals' => [
                'employees' => count($rows),
                'gross' => array_sum(array_column($rows, 'gross')),
                'tax' => array_sum(array_column($rows, 'tax')),
            ],
        ]);
    }

    public function pph21Export(Request $request): BinaryFileResponse|\Illuminate\Http\RedirectResponse
    {
        [, $period] = $this->pickPeriod($request);
        if (!$period) {
            return redirect()->route('finance.pph21.report')->with('error', 'Choose a payroll period first.');
        }
        $rows = array_map(fn ($r) => [$r['name'], $r['eci'], $r['ptkp'], $r['npwp'], $r['gross'], $r['method_label'], $r['tax'], $r['refund']], $this->pphRows($period->id));

        return Excel::download(new FinanceTableExport(
            ['Employee', 'Employee no.', 'PTKP', 'NPWP', 'Gross income for tax', 'Method', 'PPh 21', 'Over-withheld'], $rows
        ), 'pph21-report-' . $period->period_start . '_' . $period->period_end . '.xlsx');
    }

    // ── helpers ──────────────────────────────────────────────────────────────

    /** Periode yang punya slip, terbaru dulu; terpilih = ?period atau yang terbaru. @return array{0:\Illuminate\Support\Collection,1:object|null} */
    private function pickPeriod(Request $request): array
    {
        $periods = DB::table('payroll_periods')->whereNotNull('calculated_at')->orderByDesc('period_start')->get(['id', 'name', 'period_start', 'period_end', 'status', 'employee_count']);
        $id = (int) $request->query('period', 0);
        $period = $periods->firstWhere('id', $id) ?? $periods->first();

        return [$periods, $period];
    }

    /** @return array<int,array<string,mixed>> */
    private function bpjsRows(int $periodId): array
    {
        $slips = DB::table('payroll_slips')->where('period_id', $periodId)->orderBy('employee_name')->get();
        $numbers = DB::table('employee_identification')->whereIn('employee_id', $slips->pluck('employee_id'))
            ->whereIn('identification_type', ['BPJS_KESEHATAN', 'BPJS_KETENAGAKERJAAN'])
            ->get(['employee_id', 'identification_type', 'identification_number'])->groupBy('employee_id');

        return $slips->map(function ($s) use ($numbers) {
            $b = (json_decode((string) $s->breakdown, true) ?: [])['bpjs'] ?? [];
            $n = $numbers->get($s->employee_id, collect());
            $ee = ['health' => ($b['health']['employee'] ?? 0) + ($b['health']['dependents_amount'] ?? 0), 'jht' => $b['jht']['employee'] ?? 0, 'jp' => $b['jp']['employee'] ?? 0];
            $er = ['health' => $b['health']['employer'] ?? 0, 'jht' => $b['jht']['employer'] ?? 0, 'jp' => $b['jp']['employer'] ?? 0, 'jkk' => $b['jkk']['employer'] ?? 0, 'jkm' => $b['jkm']['employer'] ?? 0];

            return [
                'slip_id' => $s->id, 'name' => $s->employee_name, 'eci' => $s->employee_eci, 'department' => $s->department,
                'bpjs_health_no' => (string) optional($n->firstWhere('identification_type', 'BPJS_KESEHATAN'))->identification_number,
                'bpjs_employment_no' => (string) optional($n->firstWhere('identification_type', 'BPJS_KETENAGAKERJAAN'))->identification_number,
                'base' => (float) ($b['wage'] ?? 0), 'ee' => $ee, 'er' => $er,
                'employee_total' => (float) $s->bpjs_employee, 'employer_total' => (float) $s->bpjs_employer,
            ];
        })->all();
    }

    /** @return array<int,array<string,mixed>> */
    private function pphRows(int $periodId): array
    {
        $slips = DB::table('payroll_slips')->where('period_id', $periodId)->orderBy('employee_name')->get();
        $npwp = DB::table('employee_identification')->whereIn('employee_id', $slips->pluck('employee_id'))->where('identification_type', 'NPWP')
            ->pluck('identification_number', 'employee_id');

        return $slips->map(function ($s) use ($npwp) {
            $p = (json_decode((string) $s->breakdown, true) ?: [])['pph21'] ?? [];
            $method = $p['method'] ?? 'none';
            $label = match ($method) {
                'ter' => 'TER ' . rtrim(rtrim(number_format((float) ($p['rate'] ?? 0), 2, ',', ''), '0'), ',') . '% (cat. ' . ($p['category'] ?? '?') . ')',
                'annual_true_up' => 'Annual calculation',
                default => 'Not calculated',
            };

            return [
                'slip_id' => $s->id, 'name' => $s->employee_name, 'eci' => $s->employee_eci, 'department' => $s->department,
                'ptkp' => $s->ptkp_code, 'npwp' => (string) ($npwp[$s->employee_id] ?? ''), 'gross' => (float) $s->tax_gross,
                'tax' => (float) $s->pph21, 'refund' => (float) $s->pph21_refund, 'method' => $method, 'method_label' => $label,
            ];
        })->all();
    }
}
