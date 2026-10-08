<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kebijakan perhitungan Payroll yang dapat diatur lewat halaman Payroll → Settings (mengikuti aplikasi acuan,
 * tetapi hanya yang benar-benar dihitung mesin kita). Semua kolom punya default yang AMAN: potongan otomatis dari
 * absensi MATI sampai pemilik menyalakannya, karena modul absensi tidak pernah menulis status "alpa" dan potongan
 * dihitung dari selisih hari kerja dengan data kehadiran.
 *
 * Hanya menambah kolom; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            // Absensi
            $table->boolean('late_penalty_enabled')->default(false);
            $table->unsignedSmallInteger('late_grace_minutes')->default(10);      // toleransi tambahan di atas toleransi shift
            $table->unsignedSmallInteger('workday_base_minutes')->default(480);   // menit kerja sehari untuk tarif per menit
            $table->boolean('absence_deduction_enabled')->default(false);
            // Kategori cuti yang dibayar (selain tipe cuti yang ditandai tidak dibayar di Leave & Permit)
            $table->boolean('sick_paid')->default(true);
            $table->boolean('leave_paid')->default(true);
            $table->boolean('permit_paid')->default(true);

            // Pendapatan
            $table->boolean('include_reimbursement')->default(false);              // reimbursement disetujui ikut dibayar lewat payroll
            $table->unsignedTinyInteger('overtime_work_days')->nullable();         // null = ikut jadwal kerja payroll; 5 | 6

            // Prorata & pembagi
            $table->string('proration_basis', 20)->default('fixed_divisor');       // fixed_divisor | calendar
            $table->unsignedTinyInteger('fixed_divisor')->default(21);             // 21 | 22 | 25 | 26

            // BPJS & pajak
            $table->boolean('bpjs_enabled')->default(true);
            $table->boolean('deduct_bpjs_tk_before_pph21')->default(false);        // JHT+JP karyawan mengurangi dasar TER (bukan default PMK 168/2023)
            $table->boolean('pph21_enabled')->default(true);
            $table->boolean('apply_no_npwp_surcharge')->default(false);            // PPh 21 +20% bila tak punya NPWP/NIK
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn([
                'late_penalty_enabled', 'late_grace_minutes', 'workday_base_minutes', 'absence_deduction_enabled',
                'sick_paid', 'leave_paid', 'permit_paid', 'include_reimbursement', 'overtime_work_days',
                'proration_basis', 'fixed_divisor', 'bpjs_enabled', 'deduct_bpjs_tk_before_pph21', 'pph21_enabled',
                'apply_no_npwp_surcharge',
            ]);
        });
    }
};
