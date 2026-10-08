<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Versi awal pengaturan BPJS dari database/data/bpjs_2026.php. IDEMPOTEN & ADITIF: hanya menambah versi dengan
 * tanggal berlaku yang belum ada. down() hanya menghapus versi bawaan ini bila belum ada pembuatnya (belum diubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        foreach (require database_path('data/bpjs_2026.php') as $row) {
            if (!DB::table('bpjs_settings')->where('effective_date', $row['effective_date'])->exists()) {
                DB::table('bpjs_settings')->insert($row + ['created_at' => $now, 'updated_at' => $now]);
            }
        }
    }

    public function down(): void
    {
        $dates = array_column(require database_path('data/bpjs_2026.php'), 'effective_date');
        DB::table('bpjs_settings')->whereIn('effective_date', $dates)->whereNull('created_by')->delete();
    }
};
