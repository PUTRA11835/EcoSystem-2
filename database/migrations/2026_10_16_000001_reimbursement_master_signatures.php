<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menyetujui = menandatangani dokumen dengan tanda tangan dari master data
 * karyawan (employee_hr_profile.signature_path).
 *
 * Gambarnya DISALIN ke dokumen saat bertindak, sehingga dokumen tetap seperti
 * saat ditandatangani walau tanda tangan di master data kelak diganti.
 *
 *  - `reimbursement_request_approvals.signature_path` / `signed_at` — tanda
 *    tangan penyetuju pada langkah itu.
 *  - `reimbursement_requests.requester_signature_path` — tanda tangan pemohon
 *    saat dokumen diajukan (bila ia punya di master data).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_request_approvals', function (Blueprint $table) {
            $table->string('signature_path', 255)->nullable();
            $table->timestamp('signed_at')->nullable();
        });

        Schema::table('reimbursement_requests', function (Blueprint $table) {
            $table->string('requester_signature_path', 255)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('reimbursement_requests', fn (Blueprint $t) => $t->dropColumn('requester_signature_path'));
        Schema::table('reimbursement_request_approvals', fn (Blueprint $t) => $t->dropColumn(['signature_path', 'signed_at']));
    }
};
