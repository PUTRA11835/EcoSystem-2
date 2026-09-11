<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Grade dipindahkan konsepnya dari Basic Data ke "Level" pada Employee Qualification
 * (khusus tipe Certification) — lihat qualification_level di tabel employee_qualification.
 * Kolom ini sengaja di-drop tanpa migrasi data (keputusan pemilik produk): 91 nilai
 * grade lama yang sudah terisi tidak dipindahkan otomatis.
 *
 * Tabel `grades` (nama, md_price, sort_order, is_active) TETAP ada — dipakai sebagai
 * single source of truth untuk opsi dropdown Level di Qualification (nama di-strip
 * suffix " Consultant"). Lihat App\Models\Grade & AppServiceProvider.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->dropColumn('grade');
        });
    }

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->string('grade', 100)->nullable();
        });
    }
};
