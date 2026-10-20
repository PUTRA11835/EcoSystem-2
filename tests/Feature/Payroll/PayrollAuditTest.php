<?php

namespace Tests\Feature\Payroll;

use App\Services\Payroll\PayrollAudit;
use App\Services\Payroll\PayrollPeriodService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Locking / closing a payroll period, and the audit trail of every manual step on it. In-memory SQLite with only the tables these
 * flows read.
 */
class PayrollAuditTest extends TestCase
{
    private const ACTOR = 7;
    private const EMPLOYEE = 21;

    protected function setUp(): void
    {
        if (!extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('pdo_sqlite is not loaded (run: php -d extension=pdo_sqlite -d extension=sqlite3 vendor/bin/phpunit).');
        }
        parent::setUp();

        Schema::create('payroll_settings', function (Blueprint $t) {
            $t->unsignedTinyInteger('id')->primary();
            $t->boolean('module_enabled')->default(false);
        });
        Schema::create('payroll_periods', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->date('period_start');
            $t->date('period_end');
            $t->date('pay_date')->nullable();
            $t->string('status')->default('open');
            $t->timestamp('calculated_at')->nullable();
            $t->timestamp('locked_at')->nullable();
            $t->unsignedBigInteger('locked_by')->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('payroll_slips', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('period_id');
            $t->unsignedBigInteger('employee_id');
            $t->unsignedInteger('slip_no')->nullable();
            $t->decimal('take_home_pay', 15, 2)->default(0);
            $t->decimal('gross_earnings', 15, 2)->default(0);
        });
        Schema::create('payroll_adjustments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('period_id');
            $t->unsignedBigInteger('employee_id');
            $t->string('kind');
            $t->string('category');
            $t->unsignedBigInteger('source_period_id')->nullable();
            $t->string('component')->nullable();
            $t->string('name');
            $t->decimal('amount', 15, 2);
            $t->boolean('taxable')->default(false);
            $t->string('notes', 500)->nullable();
            $t->unsignedBigInteger('created_by')->nullable();
            $t->timestamps();
        });
        Schema::create('employee', function (Blueprint $t) {
            $t->unsignedBigInteger('employee_id')->primary();
        });
        Schema::create('employee_basic_data', function (Blueprint $t) {
            $t->unsignedBigInteger('employee_id')->primary();
            $t->string('first_name')->nullable();
            $t->string('last_name')->nullable();
        });
        Schema::create('payroll_audit_logs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('period_id')->nullable();
            $t->unsignedBigInteger('employee_id')->nullable();
            $t->string('action', 40);
            $t->string('summary', 500);
            $t->json('details')->nullable();
            $t->unsignedBigInteger('actor_id')->nullable();
            $t->timestamp('created_at')->useCurrent();
        });

        DB::table('payroll_settings')->insert(['id' => 1, 'module_enabled' => true]);
        DB::table('employee')->insert(['employee_id' => self::EMPLOYEE]);
        DB::table('employee_basic_data')->insert([
            ['employee_id' => self::ACTOR, 'first_name' => 'Rina', 'last_name' => 'Finance'],
            ['employee_id' => self::EMPLOYEE, 'first_name' => 'Budi', 'last_name' => 'Staff'],
        ]);
    }

    private function service(): PayrollPeriodService
    {
        return app(PayrollPeriodService::class);
    }

    /** An open, calculated period with one payslip. */
    private function calculatedPeriod(): int
    {
        $id = DB::table('payroll_periods')->insertGetId([
            'name' => 'Payroll October 2026', 'period_start' => '2026-10-01', 'period_end' => '2026-10-31', 'pay_date' => '2026-10-31',
            'status' => 'open', 'calculated_at' => now(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('payroll_slips')->insert(['period_id' => $id, 'employee_id' => self::EMPLOYEE, 'take_home_pay' => 9_000_000, 'gross_earnings' => 10_000_000]);

        return $id;
    }

    public function test_locking_closes_the_period_and_records_who_and_the_totals(): void
    {
        $id = $this->calculatedPeriod();

        $this->assertSame([], $this->service()->lock($id, self::ACTOR));

        $period = DB::table('payroll_periods')->find($id);
        $this->assertSame('locked', $period->status);
        $this->assertSame(self::ACTOR, (int) $period->locked_by);

        $trail = PayrollAudit::forPeriod($id);
        $this->assertCount(1, $trail);
        $this->assertSame(PayrollAudit::LOCKED, $trail[0]->action);
        $this->assertSame('Rina Finance', $trail[0]->actor_name);
        $this->assertEquals(9_000_000, $trail[0]->details['total_take_home']);
        $this->assertSame(1, $trail[0]->details['payslips']);
    }

    public function test_a_period_cannot_be_locked_twice_or_before_it_is_calculated(): void
    {
        $id = $this->calculatedPeriod();
        $this->service()->lock($id, self::ACTOR);

        $this->assertNotEmpty($this->service()->lock($id, self::ACTOR), 'a locked period cannot be locked again');

        $fresh = DB::table('payroll_periods')->insertGetId([
            'name' => 'Payroll November 2026', 'period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'status' => 'open',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->assertNotEmpty($this->service()->lock($fresh, self::ACTOR), 'nothing to lock before the first calculation');
        $this->assertSame(1, DB::table('payroll_audit_logs')->count(), 'a refused lock leaves no log row');
    }

    public function test_a_locked_period_refuses_corrections_recalculation_and_deletion(): void
    {
        $id = $this->calculatedPeriod();
        $this->service()->lock($id, self::ACTOR);
        $before = DB::table('payroll_audit_logs')->count();

        $add = $this->service()->addAdjustment($id, ['employee_id' => self::EMPLOYEE, 'category' => 'adjustment', 'kind' => 'earning', 'name' => 'Bonus', 'amount' => '100000'], self::ACTOR);
        $this->assertNotEmpty($add);
        $this->assertNotEmpty($this->service()->recalculateEmployee($id, self::EMPLOYEE, null, self::ACTOR));
        $this->assertNotEmpty($this->service()->delete($id, self::ACTOR));

        $this->assertSame(1, DB::table('payroll_periods')->where('id', $id)->count(), 'the period is still there');
        $this->assertSame(0, DB::table('payroll_adjustments')->count());
        $this->assertSame($before, DB::table('payroll_audit_logs')->count(), 'refused changes are not logged as done');
    }

    public function test_adding_and_removing_a_correction_is_logged_with_the_amount_and_the_person(): void
    {
        $id = $this->calculatedPeriod();

        $this->assertSame([], $this->service()->addAdjustment($id, [
            'employee_id' => self::EMPLOYEE, 'category' => 'adjustment', 'kind' => 'earning', 'name' => 'Project bonus', 'amount' => '250000', 'taxable' => '1',
        ], self::ACTOR));
        $adjustmentId = (int) DB::table('payroll_adjustments')->value('id');
        $this->assertSame([], $this->service()->deleteAdjustment($id, $adjustmentId, self::ACTOR));

        $trail = PayrollAudit::forPeriod($id);
        $this->assertSame([PayrollAudit::CORRECTION_REMOVED, PayrollAudit::CORRECTION_ADDED], $trail->pluck('action')->all(), 'newest first');
        $this->assertEquals(250000, $trail[1]->details['amount']);
        $this->assertSame(self::EMPLOYEE, (int) $trail[1]->employee_id);
        $this->assertSame('Project bonus', $trail[0]->details['name'], 'the removed row is described, not just gone');
        $this->assertSame(0, DB::table('payroll_adjustments')->count());
    }

    public function test_the_trail_of_a_deleted_period_survives_it(): void
    {
        $id = DB::table('payroll_periods')->insertGetId([
            'name' => 'Draft period', 'period_start' => '2026-12-01', 'period_end' => '2026-12-31', 'status' => 'open', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->assertSame([], $this->service()->delete($id, self::ACTOR));

        $this->assertSame(0, DB::table('payroll_periods')->count());
        $trail = PayrollAudit::forPeriod($id);
        $this->assertSame(PayrollAudit::DELETED, $trail[0]->action);
        $this->assertSame('Draft period', $trail[0]->details['name'], 'the name is copied into the log, so the trail still reads after the period is gone');
    }

    public function test_nothing_happens_while_the_payroll_module_is_switched_off(): void
    {
        DB::table('payroll_settings')->update(['module_enabled' => false]);
        $id = $this->calculatedPeriod();

        $this->assertNotEmpty($this->service()->lock($id, self::ACTOR));
        $this->assertSame('open', DB::table('payroll_periods')->value('status'));
        $this->assertSame(0, DB::table('payroll_audit_logs')->count());
    }
}
