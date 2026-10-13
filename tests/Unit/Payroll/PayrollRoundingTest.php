<?php

namespace Tests\Unit\Payroll;

use App\Support\Payroll\PayrollCalculator;
use App\Support\Payroll\PayrollRounding as R;
use PHPUnit\Framework\TestCase;

class PayrollRoundingTest extends TestCase
{
    public function test_mode_pembulatan(): void
    {
        $this->assertSame(9687800.0, R::apply(9687800.0, 'none', 1000));
        $this->assertSame(9687000.0, R::apply(9687800.0, 'down', 1000));
        $this->assertSame(9688000.0, R::apply(9687800.0, 'up', 1000));
        $this->assertSame(9688000.0, R::apply(9687800.0, 'nearest', 1000));
        $this->assertSame(9687000.0, R::apply(9687499.0, 'nearest', 1000));
        $this->assertSame(9687000.0, R::apply(9687000.0, 'up', 1000), 'sudah kelipatan: tidak naik');
        $this->assertSame(5.5, R::apply(5.5, 'down', 0), 'kelipatan nol diabaikan');
    }

    public function test_menit_lembur(): void
    {
        $this->assertSame(0, R::overtimeMinutes(20, 30, 0, 'nearest'), 'di bawah ambang minimum tidak dibayar');
        $this->assertSame(30, R::overtimeMinutes(30, 30, 0, 'nearest'));
        $this->assertSame(60, R::overtimeMinutes(50, 0, 30, 'up'));
        $this->assertSame(30, R::overtimeMinutes(50, 0, 30, 'down'));
        $this->assertSame(45, R::overtimeMinutes(40, 0, 15, 'nearest'));
        $this->assertSame(47, R::overtimeMinutes(47, 0, 0, 'up'), 'tanpa pembulatan menit');
    }

    public function test_pembulatan_gaji_bersih_tercatat_sebagai_baris(): void
    {
        $in = [
            'start' => '2026-10-01', 'end' => '2026-10-31',
            'components' => [['id' => 1, 'name' => 'Basic Salary', 'category' => 'base', 'amount' => 5000333.0, 'effective_from' => '2026-01-01', 'effective_to' => null, 'is_active' => true, 'taxable' => true, 'bpjs_base' => true]],
            'overtime' => [], 'reimbursements' => [], 'adjustments' => [], 'attendance' => null, 'workdays' => [],
            'employee' => ['ptkp_code' => null, 'bpjs_health_active' => false, 'bpjs_employment_active' => false, 'bpjs_dependents' => 0, 'has_tax_id' => true],
            'settings' => ['prorate_partial_period' => true, 'proration_basis' => 'calendar', 'fixed_divisor' => 21, 'bpjs_enabled' => false, 'pph21_enabled' => false],
            'bpjs' => [], 'pph21' => [], 'final_tax_period' => false, 'ytd' => ['gross' => 0, 'employee_pension' => 0, 'pph21_withheld' => 0, 'months' => 0],
        ];
        $plain = PayrollCalculator::calculate($in);
        $this->assertSame(5000333.0, $plain['summary']['take_home_pay']);

        $in['settings'] += ['net_rounding_mode' => 'down', 'net_rounding_increment' => 1000];
        $down = PayrollCalculator::calculate($in);
        $this->assertSame(5000000.0, $down['summary']['take_home_pay']);
        $line = array_values(array_filter($down['items'], fn ($i) => $i['code'] === 'rounding'))[0];
        $this->assertSame('deduction', $line['type']);
        $this->assertSame(333.0, $line['amount']);
        $this->assertSame(333.0, round($down['summary']['total_deductions'], 2), 'selisih tercatat sebagai potongan');

        $in['settings']['net_rounding_mode'] = 'up';
        $this->assertSame(5001000.0, PayrollCalculator::calculate($in)['summary']['take_home_pay']);
    }
}
