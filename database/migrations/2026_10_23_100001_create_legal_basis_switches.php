<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Saklar penerapan Register Dasar Hukum PER MODUL (`legal_basis_settings`) + pemindahan menu Legal Basis ke "Business & Legal".
 *
 *   show_panel   tampilkan panel "Legal basis" di halaman Settings modul          (bawaan MENYALA — hanya informasi)
 *   warn_banner  tampilkan spanduk peringatan di halaman kerja modul bila ada aturan belum diulas   (bawaan MENYALA)
 *   enforce      TERAPKAN: fitur modul menolak jalan selama aturan wajibnya belum berstatus Reviewed (bawaan MATI, kecuali Offboarding
 *                yang sejak awal memang menahan kalkulatornya). Payroll (pph21/bpjs/overtime): mematikan = perilaku Payroll persis seperti sebelumnya.
 *
 * ADITIF: tabel baru; satu baris `menu` dipindah induknya (slug & izin tetap, jadi tidak ada izin yang berubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('legal_basis_settings')) {
            Schema::create('legal_basis_settings', function (Blueprint $table) {
                $table->id();
                $table->string('module', 20)->unique();
                $table->boolean('show_panel')->default(true);
                $table->boolean('warn_banner')->default(true);
                $table->boolean('enforce')->default(false);
                $table->unsignedBigInteger('updated_by')->nullable();
                $table->timestamps();
            });
        }

        foreach (['pph21' => 0, 'bpjs' => 0, 'overtime' => 0, 'contract' => 0, 'offboarding' => 1] as $module => $enforce) {
            DB::table('legal_basis_settings')->insertOrIgnore([
                'module' => $module, 'show_panel' => 1, 'warn_banner' => 1, 'enforce' => $enforce, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Legal Basis dipusatkan di Business & Legal: induknya di Menu Access menjadi menu `legal`.
        $legal = DB::table('menu')->where('slug', 'legal')->value('id');
        if ($legal) {
            DB::table('menu')->where('slug', 'finance.legal-basis')->update(['parent_id' => $legal, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        $finance = DB::table('menu')->where('slug', 'finance')->value('id');
        if ($finance) {
            DB::table('menu')->where('slug', 'finance.legal-basis')->update(['parent_id' => $finance, 'updated_at' => now()]);
        }
        Schema::dropIfExists('legal_basis_settings');
    }
};
