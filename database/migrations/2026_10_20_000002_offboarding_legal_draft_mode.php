<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Offboarding memakai "Draft mode" lebih dulu (permintaan pemilik 20 Okt 2026): kalkulator penyelesaian boleh dipakai selagi
 * aturan hukumnya belum diulas Legal — hasilnya diberi tanda DRAFT, dan modul tidak lagi menolak berjalan. Hanya mengubah saklar
 * yang BELUM pernah diubah manusia (updated_by kosong); bila Admin sudah menetapkannya sendiri, tidak disentuh. Bisa dikembalikan
 * kapan saja di Legal Basis → Mode → "Enforced".
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('legal_basis_settings')->where('module', 'offboarding')->whereNull('updated_by')->where('enforce', 1)
            ->update(['enforce' => 0, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Sengaja kosong: pilihan mode adalah keputusan pemilik di halaman Legal Basis.
    }
};
