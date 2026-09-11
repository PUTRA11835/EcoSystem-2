<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Dukungan generate laporan per-fase (lihat WordReport::PHASE_* dan
 * ReportGeneratorService) -- alur lama menjalankan baca struktur + tarik
 * data + edit dokumen sebagai SATU percakapan AI panjang (sampai 12 iterasi
 * tool-calling), yang bikin context tiap giliran terus menumpuk (lambat,
 * gampang kehabisan token) dan kalau gagal di tengah jalan tidak ada
 * checkpoint (harus ulang dari nol). Sekarang tiap fase adalah panggilan AI
 * terpisah, hasilnya disimpan di sini sebelum lanjut ke fase berikutnya.
 *
 * `phase` menunjuk fase yang SEDANG/BELUM dikerjakan (null = semua fase
 * selesai); `structure_map` = hasil Tahap 1, `pulled_data` = hasil Tahap 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('word_reports', function (Blueprint $table) {
            $table->string('phase', 20)->nullable()->after('status');
            $table->json('structure_map')->nullable()->after('qa_log');
            $table->json('pulled_data')->nullable()->after('structure_map');
        });
    }

    public function down(): void
    {
        Schema::table('word_reports', function (Blueprint $table) {
            $table->dropColumn(['phase', 'structure_map', 'pulled_data']);
        });
    }
};
