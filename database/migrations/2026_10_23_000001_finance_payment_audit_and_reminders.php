<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finance follow-ups, in one migration:
 *
 * 1. PAYROLL AUDIT TRAIL (`payroll_audit_logs`) — everything done BY HAND to a payroll period: created, calculated, an employee recalculated
 *    (with the BPJS / PPh 21 switches before and after), a correction added or removed, locked, payslips generated and published, bank file
 *    downloaded, period deleted. The payroll tables are written with the query builder (no model events), so the generic model audit does
 *    not see them. `period_id` is NOT a foreign key on purpose: the trail of a deleted period must survive it. Insert-only.
 *
 * 2. REIMBURSEMENT PAYMENT — a reimbursement used to END at "approved". Now: approved + paid_at NULL = Awaiting payment; approved + paid_at
 *    set = Paid (date, reference, who). `status` is not touched (the approval flow and the payroll "reimbursements in the period" feed rely
 *    on its four values). Existing approved documents start as "awaiting payment"; Finance records the ones already paid.
 *    Menu Access: `general.reimbursement.pay` — who may record or reverse a payment (EC Administrator + HO Finance Administrator / Head).
 *
 * 3. CAR REMINDERS (`cash_advance_settings`) — a daily command (cash-advance:car-reminders) reminds the requester to file the Cash Advance
 *    Report before the deadline, on the day, and while overdue:
 *      car_reminders_enabled     master switch (on by default; no effect while the deadline is 0 = none)
 *      car_reminder_days_before  how many days before the deadline the first reminder goes out
 */
return new class extends Migration
{
    private const PAY_SLUG = 'general.reimbursement.pay';

    public function up(): void
    {
        Schema::create('payroll_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id')->nullable()->index();
            $table->unsignedBigInteger('employee_id')->nullable()->index();   // the employee a recalculation / correction concerns
            $table->string('action', 40)->index();
            $table->string('summary', 500);
            $table->json('details')->nullable();
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::table('reimbursement_requests', function (Blueprint $table) {
            $table->timestamp('paid_at')->nullable()->after('completed_at');
            $table->unsignedBigInteger('paid_by')->nullable()->after('paid_at');
            $table->string('payment_reference', 100)->nullable()->after('paid_by');
            $table->string('payment_note', 255)->nullable()->after('payment_reference');
            $table->index(['status', 'paid_at']);
        });
        if (MenuRegistrar::register('general', [self::PAY_SLUG => 'Reimbursement — Record payment'], 70, 'function')) {
            MenuRegistrar::grantToAdminAndRoles([self::PAY_SLUG], ['HO Finance Administrator', 'HO Finance Head']);
        }

        Schema::table('cash_advance_settings', function (Blueprint $table) {
            $table->boolean('car_reminders_enabled')->default(true)->after('car_due_days');
            $table->unsignedTinyInteger('car_reminder_days_before')->default(3)->after('car_reminders_enabled');
        });

        $this->flushPermCache();
    }

    public function down(): void
    {
        Schema::table('cash_advance_settings', function (Blueprint $table) {
            $table->dropColumn(['car_reminders_enabled', 'car_reminder_days_before']);
        });

        MenuRegistrar::remove([self::PAY_SLUG]);
        Schema::table('reimbursement_requests', function (Blueprint $table) {
            $table->dropIndex(['status', 'paid_at']);
            $table->dropColumn(['paid_at', 'paid_by', 'payment_reference', 'payment_note']);
        });

        Schema::dropIfExists('payroll_audit_logs');

        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
