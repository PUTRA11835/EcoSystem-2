<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * SLA policy sekarang dicocokkan hanya lewat delivery support + priority (scale tidak dipakai).
     * Per kombinasi itu hanya policy scale Medium yang dipertahankan; scale lain dinonaktifkan.
     * Kombinasi yang tidak punya policy Medium jadi tidak punya policy aktif sama sekali
     * dan perlu dibuat ulang manual dari SLA Configuration.
     */
    public function up(): void
    {
        DB::table('sla_policies')
            ->where('scale', '!=', 'Medium')
            ->where('is_active', true)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Status aktif sebelumnya tidak disimpan, jadi tidak bisa dikembalikan otomatis.
    }
};
