<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil UAT: untuk kasus pengeluaran mendadak HR perlu bisa menonaktifkan akses SEBELUM tanggal efektif.
 * Saklar baru, MATI bawaan (perilaku lama tidak berubah). Bila menyala, "Force deactivate" butuh alasan tertulis yang dicatat di riwayat.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('offboarding_settings') && ! Schema::hasColumn('offboarding_settings', 'allow_force_deactivate')) {
            Schema::table('offboarding_settings', function (Blueprint $table) {
                $table->boolean('allow_force_deactivate')->default(false)->after('require_tasks_before_deactivate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('offboarding_settings', 'allow_force_deactivate')) {
            Schema::table('offboarding_settings', fn (Blueprint $table) => $table->dropColumn('allow_force_deactivate'));
        }
    }
};
