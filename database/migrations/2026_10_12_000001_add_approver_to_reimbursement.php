<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pemilihan direktur penyetuju pada dokumen reimbursement.
 *
 *  - `reimbursement_settings.director_employee_ids` — daftar direktur yang
 *    boleh dipilih pemohon. Disimpan sebagai daftar employee_id, bukan nama,
 *    supaya pergantian direktur cukup lewat halaman Settings.
 *  - `reimbursement_requests.approver_id` — direktur yang dipilih pemohon untuk
 *    dokumen ini. NULL bila daftar direktur belum diisi (perilaku lama).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reimbursement_settings', function (Blueprint $table) {
            $table->json('director_employee_ids')->nullable();
        });

        Schema::table('reimbursement_requests', function (Blueprint $table) {
            $table->unsignedBigInteger('approver_id')->nullable()->after('created_by');

            $table->foreign('approver_id')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reimbursement_requests', function (Blueprint $table) {
            $table->dropForeign(['approver_id']);
            $table->dropColumn('approver_id');
        });

        Schema::table('reimbursement_settings', function (Blueprint $table) {
            $table->dropColumn('director_employee_ids');
        });
    }
};
