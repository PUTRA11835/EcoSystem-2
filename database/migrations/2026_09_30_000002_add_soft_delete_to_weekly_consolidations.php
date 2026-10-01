<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft-delete untuk Weekly Consolidation (Edit/Delete/Save, lihat plan
 * "Weekly Consolidation — Edit/Delete/Save + Last Update column"). Delete
 * default = soft (bisa di-restore), plus jalur hapus permanen terpisah di
 * controller (forceDelete, cascade ke weekly_consolidation_tickets lewat FK
 * yang sudah ada). Izin edit/delete sendiri TIDAK butuh migrasi baru — sudah
 * pakai kolom role_menu.can_edit/can_delete yang sudah ada, diatur lewat
 * Control Center → Menu Access.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('weekly_consolidations', function (Blueprint $table) {
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by_id')->nullable()->after('deleted_at');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_consolidations', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'deleted_by_id']);
        });
    }
};
