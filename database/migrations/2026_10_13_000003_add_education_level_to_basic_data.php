<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Master Employee → Basic Data: pendidikan terakhir (Education Level), opsional. Kolom tambahan yang boleh kosong;
 * data lama tidak berubah. Berbeda dengan tab Education (riwayat sekolah per baris).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->string('education_level', 20)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employee_basic_data', function (Blueprint $table) {
            $table->dropColumn('education_level');
        });
    }
};
