<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan multi-modul untuk Weekly Consolidation: satu batch recon kini bisa
 * mencakup lebih dari satu modul (mis. gabungan ABAP+BASIS), atau seluruh
 * modul aktif ("All"). Kapabilitas ini dibatasi ke role privileged saja
 * (lihat WeeklyConsolidationController::canCombineModules()) — module lead
 * biasa tetap satu modul per batch seperti semula.
 *
 * `weekly_consolidations.module_id` TETAP ADA sebagai modul utama/representatif
 * (yang pertama secara alfabet) supaya semua kode single-module lama tidak
 * perlu berubah — persis pola `Ticket::module_id` (utama) + `ticket_module`
 * (daftar lengkap) yang sudah dipakai di Ticket::syncModules().
 *
 * `is_all_modules` disimpan eksplisit (bukan diturunkan dari "jumlah modul
 * batch ini == jumlah modul aktif sekarang") supaya nama file export tetap
 * "ALL_..." meski daftar modul aktif berubah di kemudian hari.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('weekly_consolidations', 'is_all_modules')) {
            Schema::table('weekly_consolidations', function (Blueprint $table) {
                $table->boolean('is_all_modules')->default(false)->after('module_id');
            });
        }

        if (!Schema::hasTable('weekly_consolidation_modules')) {
            Schema::create('weekly_consolidation_modules', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('weekly_consolidation_id');
                $table->unsignedBigInteger('module_id');
                $table->timestamps();

                $table->unique(['weekly_consolidation_id', 'module_id'], 'uniq_weekly_consol_module');

                $table->foreign('weekly_consolidation_id', 'fk_weekly_consol_mod_header')
                    ->references('id')->on('weekly_consolidations')->onDelete('cascade');
                $table->foreign('module_id', 'fk_weekly_consol_mod_module')
                    ->references('id')->on('modules')->onDelete('cascade');
            });

            // Backfill batch lama (dari testing Phase 1) supaya pivot ini konsisten
            // jadi sumber kebenaran untuk semua batch, lama maupun baru.
            DB::statement('INSERT INTO weekly_consolidation_modules (weekly_consolidation_id, module_id, created_at, updated_at)
                SELECT id, module_id, NOW(), NOW() FROM weekly_consolidations');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('weekly_consolidation_modules');

        if (Schema::hasColumn('weekly_consolidations', 'is_all_modules')) {
            Schema::table('weekly_consolidations', function (Blueprint $table) {
                $table->dropColumn('is_all_modules');
            });
        }
    }
};
