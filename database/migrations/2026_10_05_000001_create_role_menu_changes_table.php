<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat perubahan izin role (Menu Access) — HC-D63/D64.
 *
 * KENAPA: dulu setiap centang di Menu Access langsung menulis `role_menu` tanpa jejak; salah klik tidak bisa
 * ditelusuri atau dibatalkan. Tiap "Save" di halaman baru menulis SATU baris di sini berisi perubahan per menu
 * (keadaan sebelum & sesudah) sehingga dapat ditampilkan sebagai riwayat dan DIBATALKAN (undo = perubahan baru
 * yang membalik baris ini, bukan penghapusan riwayat).
 *
 * Tanpa foreign key (sengaja): riwayat tetap utuh walau role/menu kelak dihapus. Hanya menambah tabel; down() menghapusnya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('role_menu_changes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->index();
            $table->unsignedBigInteger('actor_employee_id')->nullable();
            $table->string('kind', 20)->default('apply');            // apply | undo
            $table->json('summary');                                 // {grant, revoke, change, total}
            $table->json('changes');                                 // [{menu_id, slug, name, before, after}]
            $table->string('reason', 255)->nullable();
            $table->unsignedBigInteger('reverts_change_id')->nullable();
            $table->unsignedBigInteger('reverted_by_change_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('role_menu_changes');
    }
};
