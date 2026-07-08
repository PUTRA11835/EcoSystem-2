<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel referensi wilayah administratif Indonesia (Kepmendagri No 300.2.2-2138
 * Tahun 2025). Struktur hierarkis dalam satu tabel via panjang `kode`:
 *   11              → Provinsi          (Region)
 *   11.01           → Kabupaten/Kota    (City)
 *   11.01.01        → Kecamatan         (District)
 *   11.01.01.2001   → Kelurahan/Desa    (Rural/Urban Village)
 * Dipakai untuk dropdown alamat cascading pada Master Employee.
 * Data diimpor oleh WilayahSeeder dari database/data/wilayah.sql.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wilayah', function (Blueprint $table) {
            $table->string('kode', 13)->primary();
            $table->string('nama', 100);
            $table->index('nama', 'wilayah_name_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wilayah');
    }
};
