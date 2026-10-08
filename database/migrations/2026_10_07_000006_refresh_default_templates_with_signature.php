<?php

use App\Support\Contracts\DefaultTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Menu Contract (HC-D69): baris tanda tangan template bawaan memuat {{tanda_tangan_penandatangan}} — gambar tanda tangan
 * penandatangan dari My Profile (HR Profile), tampil begitu kontrak bukan Draft. Seperti migrasi …000005: hanya template
 * bawaan yang belum pernah disunting yang ditimpa; kontrak Active tidak terpengaruh (teksnya sudah dibekukan).
 *
 * down(): tidak memulihkan teks lama.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DefaultTemplates::all() as $template) {
            DB::table('contract_templates')
                ->where('is_system_default', 1)
                ->where('contract_type', $template['contract_type'])
                ->whereNull('updated_by')
                ->update(['body_html' => $template['body_html'], 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        // teks lama tidak disimpan; tidak ada yang dipulihkan
    }
};