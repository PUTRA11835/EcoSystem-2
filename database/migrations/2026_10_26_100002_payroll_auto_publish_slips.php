<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil UAT (Paystub): saklar opsional "Publish payslips automatically after calculating". MATI bawaan → alur sekarang
 * (Calculate → Generate Slip → Publish, tiap langkah ditekan Finance) tidak berubah. Bila menyala, selesai menghitung periode, slip
 * otomatis diberi nomor (Generate) dan dipublikasikan sehingga langsung tampil di ESS → Paystub karyawan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payroll_settings') && ! Schema::hasColumn('payroll_settings', 'auto_publish_slips')) {
            Schema::table('payroll_settings', fn (Blueprint $t) => $t->boolean('auto_publish_slips')->default(false)->after('calculate_in_background'));
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('payroll_settings', 'auto_publish_slips')) {
            Schema::table('payroll_settings', fn (Blueprint $t) => $t->dropColumn('auto_publish_slips'));
        }
    }
};
