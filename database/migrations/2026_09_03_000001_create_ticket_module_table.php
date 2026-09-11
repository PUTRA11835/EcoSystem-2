<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Satu tiket bisa menyentuh lebih dari satu modul SAP sekaligus — pivot ini
 * menyimpan daftar LENGKAPnya. `ticket.module_id` (kolom lama, scalar) TETAP
 * ADA dan tetap diisi sebagai "modul utama" (modul pertama yang dipilih) —
 * lihat Ticket::syncModules() — supaya ~20 tempat lama yang masih baca
 * module_id sebagai nilai tunggal (badge, laporan, dll) tidak perlu diubah
 * sama sekali. Pola & nama kolom mengikuti persis delivery_support_modules
 * (lihat 2026_08_19_000002_create_delivery_support_modules_table.php), yang
 * sudah lebih dulu memakai bentuk ini untuk kasus serupa (DeliverySupport
 * punya banyak Module).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ticket_module', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('ticket_id');
            $table->unsignedBigInteger('module_id');
            $table->timestamps();

            $table->unique(['ticket_id', 'module_id']);
            $table->foreign('ticket_id')->references('ticket_id')->on('ticket')->onDelete('cascade');
            $table->foreign('module_id')->references('id')->on('modules')->onDelete('cascade');
        });

        // Backfill: setiap tiket yang sudah punya module_id harus langsung
        // punya baris pivot yang sesuai, supaya query yang mulai memakai
        // pivot (AiTicketAnalyzerService, TicketTeamAccess, filter list)
        // tetap melihat data lama dengan benar — bukan cuma tiket baru yang
        // dibuat setelah migrasi ini.
        $now = now();
        DB::table('ticket')
            ->whereNotNull('module_id')
            ->select('ticket_id', 'module_id')
            ->orderBy('ticket_id')
            ->chunkById(500, function ($rows) use ($now) {
                $insert = $rows->map(fn ($row) => [
                    'ticket_id' => $row->ticket_id,
                    'module_id' => $row->module_id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ])->all();

                // insertOrIgnore: module_id yang sudah tidak ada di tabel modules
                // (data lama yang yatim) akan gagal FK constraint-nya dan dilewati
                // begitu saja, bukan menggagalkan seluruh migrasi.
                DB::table('ticket_module')->insertOrIgnore($insert);
            }, 'ticket_id');
    }

    public function down(): void
    {
        Schema::dropIfExists('ticket_module');
    }
};
