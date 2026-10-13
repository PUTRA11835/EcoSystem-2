<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Jenis karyawan (Internal / External) kini ditentukan EMPLOYEE GROUP, bukan Home Base (keputusan pemilik 13 Okt 2026).
 * Data disesuaikan SATU kali, tanpa menghapus apa pun:
 *  1. Group kosong → INTERNAL (kecuali yang selama ini berjenis External → EXTERNAL, supaya tidak berubah diam-diam).
 *  2. employee_type disinkronkan dari group: EXTERNAL → External, selain itu Internal.
 * down(): tidak dapat dipulihkan otomatis (group kosong dan home base tetap), sehingga dibiarkan kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('employee_basic_data')->where(fn ($q) => $q->whereNull('employee_group')->orWhere('employee_group', ''))
            ->where('employee_type', 'External')->update(['employee_group' => 'EXTERNAL']);
        DB::table('employee_basic_data')->where(fn ($q) => $q->whereNull('employee_group')->orWhere('employee_group', ''))
            ->update(['employee_group' => 'INTERNAL']);

        DB::table('employee_basic_data')->where('employee_group', 'EXTERNAL')->where(fn ($q) => $q->whereNull('employee_type')->orWhere('employee_type', '!=', 'External'))
            ->update(['employee_type' => 'External']);
        DB::table('employee_basic_data')->where('employee_group', '!=', 'EXTERNAL')->where(fn ($q) => $q->whereNull('employee_type')->orWhere('employee_type', '!=', 'Internal'))
            ->update(['employee_type' => 'Internal']);
    }

    public function down(): void
    {
        // Sengaja kosong: lihat catatan di atas.
    }
};
