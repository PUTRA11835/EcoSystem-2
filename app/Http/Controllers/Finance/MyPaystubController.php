<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Payroll\PayslipPdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * ESS → Paystub: karyawan melihat dan mengunduh HANYA slip miliknya yang sudah dipublikasikan Finance.
 * Tidak memakai slug menu: yang dijaga adalah kepemilikan (employee_id sesi) dan status `published_at`, bukan peran.
 */
class MyPaystubController extends Controller
{
    public function index(): View
    {
        $slips = DB::table('payroll_slips as s')->join('payroll_periods as p', 'p.id', '=', 's.period_id')
            ->where('s.employee_id', (int) session('user.id'))->whereNotNull('s.published_at')
            ->orderByDesc('p.period_end')
            ->get(['s.id', 's.period_id', 's.slip_no', 's.gross_earnings', 's.total_deductions', 's.take_home_pay', 's.published_at', 'p.name', 'p.period_start', 'p.period_end', 'p.pay_date']);

        return view('finance.payroll.my-paystub', ['slips' => $slips]);
    }

    public function pdf(Request $request, int $slip)
    {
        $row = DB::table('payroll_slips')->where('id', $slip)->where('employee_id', (int) session('user.id'))->whereNotNull('published_at')->first();
        abort_unless($row, 404);
        $pdf = PayslipPdf::make((int) $row->period_id, $slip, $request->query('lang'));
        abort_unless($pdf, 404);
        $p = DB::table('payroll_periods')->where('id', $row->period_id)->first();

        return $pdf->download(PayslipPdf::fileName($p, $row));
    }
}
