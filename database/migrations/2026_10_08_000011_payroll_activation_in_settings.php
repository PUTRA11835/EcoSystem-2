<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar aktivasi Payroll pindah dari `.env` (HC_PAYROLL_ENABLED) ke Payroll → Settings (HC-D21).
 *
 * Alasan: pihak yang berwenang (bukan developer) dapat menyalakan modul lewat website, tanpa deploy/ubah berkas server;
 * setiap perubahan tercatat (siapa & kapan, plus jejak audit lewat model PayrollSetting). Pengamannya diganti:
 *  - slug fungsi TERPISAH `finance.payroll.activate` (sensitif; hanya EC Administrator pada awalnya),
 *  - default MATI,
 *  - layar meminta konfirmasi dengan daftar pemeriksaan sebelum dinyalakan.
 *
 * Hanya menambah kolom/menu; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->boolean('module_enabled')->default(false)->after('id');
            $table->timestamp('enabled_at')->nullable();
            $table->unsignedBigInteger('enabled_by')->nullable();
        });

        MenuRegistrar::register('finance.payroll', ['finance.payroll.activate' => 'Switch payroll on/off'], 20, 'function');
        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.payroll.activate']);
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['module_enabled', 'enabled_at', 'enabled_by']);
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
