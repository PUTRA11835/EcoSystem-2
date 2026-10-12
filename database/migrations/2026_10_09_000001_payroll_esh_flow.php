<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Alur Payroll mengikuti aplikasi acuan ESH (keputusan Reinaldy, 9 Okt 2026):
 *
 *   Periode : open ──Lock──▶ locked            (Approve / Mark paid / Reopen dihapus)
 *   Slip    : calculated ──Generate Slip──▶ generated   (nomor slip + kode verifikasi)
 *
 * Perubahan (semuanya aditif; data lama dipetakan, tidak dihapus):
 *  - payroll_periods   : periode absensi terpisah + tanggal cut-off; jejak Generate Slip. Status approved → open, paid → locked.
 *  - payroll_slips     : slip_no, slip_generated_*, verification_code. Status draft/approved → calculated.
 *  - payroll_employee_overrides : saklar BPJS Kesehatan / BPJS TK / PPh 21 per karyawan per periode ("Terapkan & Hitung Ulang").
 *  - payroll_adjustments        : jadi "Koreksi Payroll": kategori (adjustment | underpaid | overpaid), periode asal, komponen.
 *  - payroll_settings  : penandatangan slip (HR & Finance) dan bahasa slip bawaan (id | en).
 * Menu: slug approve & pay dihapus; slug fungsi baru `finance.payroll.slip` (Generate Slip).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->date('attendance_start')->nullable()->after('pay_date');
            $table->date('attendance_end')->nullable()->after('attendance_start');
            $table->date('cutoff_date')->nullable()->after('attendance_end');
            $table->timestamp('slips_generated_at')->nullable();
            $table->unsignedBigInteger('slips_generated_by')->nullable();
        });
        DB::table('payroll_periods')->where('status', 'approved')->update(['status' => 'open', 'approved_at' => null, 'approved_by' => null]);
        DB::table('payroll_periods')->where('status', 'paid')->update(['status' => 'locked']);

        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->unsignedInteger('slip_no')->nullable()->after('employee_id');
            $table->timestamp('slip_generated_at')->nullable();
            $table->unsignedBigInteger('slip_generated_by')->nullable();
            $table->string('verification_code', 64)->nullable();
            $table->index('slip_no', 'idx_payroll_slips_slip_no');
        });
        DB::table('payroll_slips')->whereIn('status', ['draft', 'approved'])->update(['status' => 'calculated']);

        Schema::create('payroll_employee_overrides', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('period_id');
            $table->unsignedBigInteger('employee_id');
            $table->boolean('bpjs_health')->nullable();        // null = ikut data master
            $table->boolean('bpjs_employment')->nullable();
            $table->boolean('pph21')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['period_id', 'employee_id'], 'uq_payroll_overrides_period_employee');
            $table->foreign('period_id', 'fk_payroll_overrides_period')->references('id')->on('payroll_periods')->cascadeOnDelete();
            $table->foreign('employee_id', 'fk_payroll_overrides_employee')->references('employee_id')->on('employee');
        });

        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->string('category', 20)->default('adjustment')->after('kind');       // adjustment | underpaid | overpaid
            $table->unsignedBigInteger('source_period_id')->nullable()->after('category');
            $table->string('component', 150)->nullable()->after('source_period_id');
            $table->foreign('source_period_id', 'fk_payroll_adj_source_period')->references('id')->on('payroll_periods')->nullOnDelete();
        });

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->string('hr_signer_name', 150)->nullable();
            $table->string('hr_signer_title', 150)->nullable();
            $table->string('finance_signer_name', 150)->nullable();
            $table->string('finance_signer_title', 150)->nullable();
            $table->string('slip_language', 2)->default('id');
        });

        MenuRegistrar::remove(['finance.payroll.approve', 'finance.payroll.pay']);
        MenuRegistrar::register('finance.payroll', ['finance.payroll.slip' => 'Generate payslips'], 11, 'function');
        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.payroll.slip']);
        MenuRegistrar::register('finance.payroll', ['finance.payroll.approve' => 'Approve payroll', 'finance.payroll.pay' => 'Mark payroll paid'], 10, 'function');

        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['hr_signer_name', 'hr_signer_title', 'finance_signer_name', 'finance_signer_title', 'slip_language']);
        });
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->dropForeign('fk_payroll_adj_source_period');
            $table->dropColumn(['category', 'source_period_id', 'component']);
        });
        Schema::dropIfExists('payroll_employee_overrides');
        Schema::table('payroll_slips', function (Blueprint $table) {
            $table->dropIndex('idx_payroll_slips_slip_no');
            $table->dropColumn(['slip_no', 'slip_generated_at', 'slip_generated_by', 'verification_code']);
        });
        Schema::table('payroll_periods', function (Blueprint $table) {
            $table->dropColumn(['attendance_start', 'attendance_end', 'cutoff_date', 'slips_generated_at', 'slips_generated_by']);
        });
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
