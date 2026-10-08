<?php

use App\Support\Contracts\DefaultTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Contract (HC-D66) — template teks kontrak dan penomor kontrak.
 *
 *   contract_templates          teks kontrak dengan {{placeholder}}. Satu jenis (PKWT/PKWTT/EXTERNAL); `position` kosong =
 *                               semua Position. `is_system_default` = bawaan sistem: boleh diedit, tidak boleh dihapus.
 *                               `use_letterhead`: pakai kop surat dari Letter Templates → Settings (jenis surat "Employment Contract").
 *   contract_number_sequences   urut nomor kontrak per (jenis, tahun); naik dalam transaksi dengan kunci baris, tak dipakai ulang.
 *
 * Tiga template bawaan disemai di sini (kerangka; isi pasal menyusul).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contract_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('contract_type', 20);
            $table->string('position', 255)->nullable();
            $table->string('description', 500)->nullable();
            $table->longText('body_html');
            $table->boolean('use_letterhead')->default(true);
            $table->boolean('is_system_default')->default(false);
            $table->string('status', 10)->default('active');
            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->index(['contract_type', 'status'], 'contract_templates_type_status_index');
        });

        Schema::create('contract_number_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('contract_type', 20);
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();

            $table->unique(['contract_type', 'year'], 'contract_number_sequences_type_year_unique');
        });

        $now = now();
        foreach (DefaultTemplates::all() as $template) {
            DB::table('contract_templates')->insert($template + [
                'use_letterhead' => true, 'is_system_default' => true, 'status' => 'active',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contract_number_sequences');
        Schema::dropIfExists('contract_templates');
    }
};
