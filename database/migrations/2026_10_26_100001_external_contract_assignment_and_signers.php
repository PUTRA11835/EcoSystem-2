<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil UAT (External Contract):
 *  1. kolom `assignment` pada ketentuan kontrak External: penugasan (proyek / ruang lingkup) kini diisi di kontrak itu sendiri,
 *     bukan hanya di Engagement konsultan; dicetak pada placeholder {{penugasan}} bila diisi.
 *  2. Pak Arbin (ECI K19002) dan Pak Titus (ECI S14027) didaftarkan sebagai penandatangan (Letter Templates → Settings → Signers) dan
 *     Arbin menjadi penandatangan bawaan template kontrak External. Hanya MENAMBAH baris bila belum ada; bila orangnya tidak ditemukan
 *     atau tidak memegang peran yang dicari, bagian itu dilewati tanpa galat (instalasi lain).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('external_contract_terms') && ! Schema::hasColumn('external_contract_terms', 'assignment')) {
            Schema::table('external_contract_terms', function (Blueprint $table) {
                $table->string('assignment', 255)->nullable()->after('payment_term_days');
            });
        }

        $this->registerSigner('K19002', 'Delivery RPMO Head', 'Head of RPMO');
        $this->registerSigner('S14027', 'EC Director', 'Vice Director Project & Operational');

        // Penandatangan bawaan template kontrak External = Arbin (hanya bila template itu belum punya penandatangan bawaan)
        $arbin = DB::table('employee')->where('eci', 'K19002')->value('employee_id');
        if ($arbin && Schema::hasTable('contract_templates') && Schema::hasColumn('contract_templates', 'signatory_employee_id')) {
            DB::table('contract_templates')->where('contract_type', 'EXTERNAL')->whereNull('signatory_employee_id')
                ->update(['signatory_employee_id' => $arbin, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('external_contract_terms', 'assignment')) {
            Schema::table('external_contract_terms', fn (Blueprint $table) => $table->dropColumn('assignment'));
        }
        // Data penandatangan sengaja tidak dihapus: bisa sudah dipakai surat/kontrak.
    }

    /** Tambah orang itu sebagai penandatangan di bawah salah satu perannya (bila belum terdaftar). */
    private function registerSigner(string $eci, string $roleName, string $title): void
    {
        if (! Schema::hasTable('letter_signatories')) {
            return;
        }
        $employeeId = DB::table('employee')->where('eci', $eci)->value('employee_id');
        if (! $employeeId) {
            return;
        }
        $already = DB::table('letter_signatories')->where('employee_id', $employeeId)->exists();
        if ($already) {
            return;
        }
        $roleId = DB::table('employee_role')->where('name', $roleName)->value('id');
        $holds = $roleId && DB::table('employee_role_assignment')->where('employee_id', $employeeId)->where('role_id', $roleId)->exists();
        if (! $holds) {
            return;
        }
        DB::table('letter_signatories')->insert([
            'role_id' => $roleId, 'employee_id' => $employeeId, 'title' => $title, 'is_active' => 1,
            'sort_order' => (int) DB::table('letter_signatories')->max('sort_order') + 1, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
};
