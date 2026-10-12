<?php

namespace App\Services\Payroll;

use App\Models\Letters\LetterSetting;
use App\Models\PayrollSetting;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;

/** Membuat PDF slip gaji (format ESH, Indonesia/Inggris) — dipakai Finance (semua slip) dan ESS Paystub (slip sendiri). */
class PayslipPdf
{
    /** @return \Barryvdh\DomPDF\PDF|null null bila slip tidak ditemukan */
    public static function make(int $periodId, int $slipId, ?string $lang = null): ?\Barryvdh\DomPDF\PDF
    {
        $p = DB::table('payroll_periods')->where('id', $periodId)->first();
        $s = DB::table('payroll_slips')->where('period_id', $periodId)->where('id', $slipId)->first();
        if (!$p || !$s) {
            return null;
        }
        $items = DB::table('payroll_slip_items')->where('slip_id', $slipId)->orderBy('sort')->get();
        $set = PayrollSetting::current();
        $lang = in_array($lang, ['id', 'en'], true) ? $lang : ($set->slip_language === 'en' ? 'en' : 'id');
        $emp = DB::table('employee_basic_data')->where('employee_id', $s->employee_id)->first(['since_date', 'employee_type']);

        return Pdf::loadView('finance.payroll.slip-pdf', [
            'p' => $p, 's' => $s, 'items' => $items, 'lang' => $lang, 'set' => $set, 'company' => LetterSetting::current(), 'emp' => $emp,
            'breakdown' => json_decode((string) $s->breakdown, true) ?: [],
        ])->setPaper('a4');
    }

    public static function fileName(object $p, object $s): string
    {
        return 'payslip-' . preg_replace('/[^A-Za-z0-9_-]+/', '-', $s->employee_name) . '-' . $p->period_end . '.pdf';
    }
}
