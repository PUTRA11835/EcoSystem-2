<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Payroll: (1) pemeriksaan kesiapan masukan (lembur/cuti/koreksi absensi/reimbursement yang belum disetujui pada periode) dengan
 * saklar opsional "tolak hitung selama masih ada yang menunggu"; (2) hitung periode di latar belakang lewat antrean (opsional).
 * Semua bawaan MATI → perilaku sekarang tidak berubah. Aditif.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $t) {
            $t->boolean('block_on_pending_inputs')->default(false);
            $t->boolean('calculate_in_background')->default(false);
        });
        Schema::table('payroll_periods', function (Blueprint $t) {
            $t->string('calc_status', 12)->nullable();     // queued | running | done | failed (kosong = hitung biasa)
            $t->text('calc_error')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_periods', fn (Blueprint $t) => $t->dropColumn(['calc_status', 'calc_error']));
        Schema::table('payroll_settings', fn (Blueprint $t) => $t->dropColumn(['block_on_pending_inputs', 'calculate_in_background']));
    }
};
