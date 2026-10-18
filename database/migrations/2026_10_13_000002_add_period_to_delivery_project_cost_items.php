<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Period (bulan + tahun) pada expense detail Plan Cost, disimpan sebagai
 * "YYYY-MM" supaya bisa difilter & diurutkan tanpa konversi.
 *
 * Nullable: expense lama tidak punya period dan sengaja TIDAK diisi otomatis
 * dari created_at — tanggal input belum tentu sama dengan period biayanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_project_cost_items') || Schema::hasColumn('delivery_project_cost_items', 'period')) {
            return;
        }

        Schema::table('delivery_project_cost_items', function (Blueprint $t) {
            $t->string('period', 7)->nullable()->after('amount');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('delivery_project_cost_items') || !Schema::hasColumn('delivery_project_cost_items', 'period')) {
            return;
        }

        Schema::table('delivery_project_cost_items', function (Blueprint $t) {
            $t->dropColumn('period');
        });
    }
};
