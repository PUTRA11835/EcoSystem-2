<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

/**
 * Master data "Deliverable Document Type" — dulu hardcoded di
 * TicketDeliverableController::DOC_TYPES dan di dropdown "New Document"
 * (resources/views/ticket/show.blade.php). Dipindah ke master data supaya
 * admin bisa menambah/menonaktifkan tipe dokumen tanpa deploy kode, mirror
 * pola Module master data (management/employee/qualification).
 *
 * `doc_type` di ticket_deliverables tetap kolom string biasa (bukan FK) —
 * lihat 2026_05_07_081939_create_ticket_deliverables_table — jadi menghapus
 * sebuah tipe di sini TIDAK mengubah dokumen yang sudah tersimpan, hanya
 * menghilangkannya dari pilihan dropdown ke depan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deliverable_document_types', function (Blueprint $table) {
            $table->id();
            $table->string('name', 50)->unique();
            $table->string('description', 255)->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('order_seq')->default(0);
            $table->timestamps();
        });

        // Seed dengan daftar hardcoded lama supaya tiket yang sudah ada &
        // dropdown yang sedang berjalan tidak kehilangan opsi apa pun.
        $defaults = ['IR', 'RCA', 'CR Form', 'FSD', 'TD', 'UAT', 'MOM', 'BAST', 'EWA', 'Other'];
        $now      = now();

        foreach ($defaults as $i => $name) {
            DB::table('deliverable_document_types')->insert([
                'name'       => $name,
                'is_active'  => true,
                'order_seq'  => $i + 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deliverable_document_types');
    }
};
