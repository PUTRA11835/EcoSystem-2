<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu mekanisme untuk peserta dokumen: ALUR PERSETUJUAN.
 *
 * Accounting, Cashier dan Approver bukan daftar/penanda tangan terpisah lagi,
 * melainkan langkah pada alur, dengan dua atribut tambahan:
 *
 *  - `signs_as`            kolom tanda tangan yang diisi langkah ini
 *                          (accounting | cashier | approver | NULL)
 *  - `requester_selectable` pemohon memilih SIAPA yang bertindak pada langkah
 *                          ini, dari kandidat langkahnya
 *
 * Nama yang tercetak diambil dari orang yang BENAR-BENAR bertindak pada langkah
 * itu, jadi tidak ada lagi dua sumber kebenaran yang bisa saling bertentangan.
 *
 * Kolom lama (`*_signer_employee_id`, `director_employee_ids`) dibiarkan di
 * tabelnya tanpa dipakai; daftar direktur yang sudah diisi diubah menjadi satu
 * langkah "Director Approval" yang dipilih pemohon, supaya perilakunya tetap.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Sisa percobaan sebelumnya (daftar peserta terpisah) dibuang bila ada.
        foreach (['accounting_id', 'cashier_id'] as $column) {
            if (Schema::hasColumn('reimbursement_requests', $column)) {
                Schema::table('reimbursement_requests', function (Blueprint $table) use ($column) {
                    $table->dropForeign([$column]);
                    $table->dropColumn($column);
                });
            }
        }

        $listColumns = array_values(array_filter(
            ['requester_employee_ids', 'accounting_employee_ids', 'cashier_employee_ids'],
            fn ($c) => Schema::hasColumn('reimbursement_settings', $c)
        ));
        if ($listColumns !== []) {
            Schema::table('reimbursement_settings', fn (Blueprint $t) => $t->dropColumn($listColumns));
        }

        Schema::table('reimbursement_approval_steps', function (Blueprint $table) {
            $table->string('signs_as', 20)->nullable()->after('approver_employee_ids');
            $table->boolean('requester_selectable')->default(false)->after('signs_as');
        });

        Schema::table('reimbursement_request_approvals', function (Blueprint $table) {
            $table->string('signs_as', 20)->nullable()->after('approver_employee_ids');
        });

        $this->convertDirectorList();
    }

    public function down(): void
    {
        Schema::table('reimbursement_request_approvals', fn (Blueprint $t) => $t->dropColumn('signs_as'));
        Schema::table('reimbursement_approval_steps', fn (Blueprint $t) => $t->dropColumn(['signs_as', 'requester_selectable']));
    }

    /** Daftar direktur lama -> satu langkah yang dipilih pemohon. */
    private function convertDirectorList(): void
    {
        if (!Schema::hasColumn('reimbursement_settings', 'director_employee_ids')) {
            return;
        }

        $raw = DB::table('reimbursement_settings')->value('director_employee_ids');
        $ids = array_values(array_unique(array_map('intval', json_decode((string) $raw, true) ?: [])));

        if ($ids === []) {
            return;
        }

        $next = (int) DB::table('reimbursement_approval_steps')->where('module', 'reimbursement')->max('order_seq') + 1;

        DB::table('reimbursement_approval_steps')->insert([
            'module'                => 'reimbursement',
            'order_seq'             => $next,
            'name'                  => 'Director Approval',
            'approver_type'         => 'employee',
            'approver_role_id'      => null,
            'approver_employee_ids' => json_encode($ids),
            'signs_as'              => 'approver',
            'requester_selectable'  => true,
            'is_active'             => true,
            'created_at'            => now(),
            'updated_at'            => now(),
        ]);
    }
};
