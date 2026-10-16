<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dua pengaturan Reimbursement:
 *
 *  - `receipt_max_age_days` / `receipt_age_policy` — batas jarak antara tanggal
 *    transaksi (nota) dan tanggal pengajuan. 0 = tanpa batas. Nota yang lebih
 *    baru daripada tanggal pengajuan selalu ditolak.
 *  - `letterhead_id` — kop surat dari menu Letter Templates untuk cetakan
 *    dokumen. NULL = kertas polos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('receipt_max_age_days')->default(0);
            $table->string('receipt_age_policy', 10)->default('flag'); // flag | block
            $table->unsignedBigInteger('letterhead_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reimbursement_settings', function (Blueprint $table) {
            $table->dropColumn(['receipt_max_age_days', 'receipt_age_policy', 'letterhead_id']);
        });
    }
};
