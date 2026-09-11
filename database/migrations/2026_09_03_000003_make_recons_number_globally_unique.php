<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Nomor Recons berganti format menjadi MDRC-[customer_code]-[yymm]-[xxxx]
 * dengan counter GLOBAL yang reset tiap tahun (keputusan pemilik sistem,
 * 2026-09-03). Karena urutannya tidak lagi per Delivery Support, keunikan
 * nomor juga harus dijaga lintas support — bukan hanya di dalam satu support.
 *
 * Unique lama : (delivery_support_id, recons_number)
 * Unique baru : (recons_number)
 *
 * Aman dijalankan pada data yang sudah ada: nomor lama berformat
 * RCN-{support}-NNN sudah memuat id support sehingga tidak mungkin bentrok
 * antar support. Kalau ternyata ADA duplikat (mis. hasil impor manual),
 * migrasi berhenti dengan pesan jelas alih-alih menulis indeks separuh jalan.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('delivery_support_recons')) {
            return;
        }

        $duplicates = DB::table('delivery_support_recons')
            ->select('recons_number', DB::raw('COUNT(*) as total'))
            ->groupBy('recons_number')
            ->having('total', '>', 1)
            ->pluck('total', 'recons_number');

        if ($duplicates->isNotEmpty()) {
            throw new RuntimeException(
                'Tidak bisa membuat recons_number unik global — masih ada nomor kembar: '
                . $duplicates->keys()->implode(', ')
                . '. Perbaiki/rename nomor tersebut lebih dulu, lalu jalankan ulang migrasi ini.'
            );
        }

        Schema::table('delivery_support_recons', function (Blueprint $table) {
            $table->dropUnique('uniq_support_recons_number');
            $table->unique('recons_number', 'uniq_recons_number');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('delivery_support_recons')) {
            return;
        }

        Schema::table('delivery_support_recons', function (Blueprint $table) {
            $table->dropUnique('uniq_recons_number');
            $table->unique(['delivery_support_id', 'recons_number'], 'uniq_support_recons_number');
        });
    }
};
