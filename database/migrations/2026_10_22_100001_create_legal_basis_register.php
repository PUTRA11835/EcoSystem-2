<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Register Dasar Hukum: peraturan yang mendasari tiap aturan perhitungan (PPh 21, BPJS, lembur, PKWT, Offboarding) — nomor, pasal,
 * berlaku sejak, TERAKHIR DIUBAH, sumber, status ulasan, dan (untuk kalkulator) tabel tarif/pengali dalam `payload`.
 *
 * ADITIF: tabel baru + satu slug menu (`finance.legal-basis`, kotak C/E/D). Tidak ada tabel/angka/rumus Payroll yang diubah.
 * Versi baru sebuah aturan = baris baru dengan `effective_date` baru (riwayat tidak dihapus). Isi awal: database/data/legal_basis_initial.php
 * — SEMUA `needs_review` sampai diulas manusia.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_basis_register', function (Blueprint $table) {
            $table->id();
            $table->string('rule_key', 60);                          // mis. pph21.ter, bpjs.jp, offboarding.severance_tax
            $table->string('module', 20);                            // pph21 | bpjs | overtime | contract | offboarding
            $table->string('regulation_type', 20);                   // UU | PP | Perpres | PMK | Kepmenaker | Surat
            $table->string('number', 40);
            $table->unsignedSmallInteger('year');
            $table->string('title', 400);
            $table->string('article', 400)->nullable();
            $table->date('effective_date');
            $table->date('last_amended_date')->nullable();           // kapan peraturan ini TERAKHIR diubah
            $table->string('amended_by', 400)->nullable();           // peraturan yang mengubahnya
            $table->string('source_url', 500)->nullable();
            $table->string('status', 14)->default('needs_review');   // active | needs_review | superseded
            $table->string('confidence', 8)->default('medium');      // keyakinan penyusun: high | medium | low
            $table->json('payload')->nullable();                     // tarif/pengali yang dibaca kalkulator
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['rule_key', 'effective_date'], 'uq_legal_rule_version');
            $table->index(['module', 'status'], 'idx_legal_module_status');
        });

        $now = now();
        foreach (require database_path('data/legal_basis_initial.php') as $row) {
            DB::table('legal_basis_register')->insertOrIgnore([
                'rule_key' => $row['rule_key'], 'module' => $row['module'], 'regulation_type' => $row['regulation_type'], 'number' => $row['number'],
                'year' => $row['year'], 'title' => $row['title'], 'article' => $row['article'], 'effective_date' => $row['effective_date'],
                'last_amended_date' => $row['last_amended_date'], 'amended_by' => $row['amended_by'], 'source_url' => null,
                'status' => 'needs_review', 'confidence' => $row['confidence'], 'payload' => $row['payload'] === null ? null : json_encode($row['payload']),
                'notes' => $row['notes'], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Menu Access: halaman kelola register (kotak C/E/D ditegakkan). Hanya EC Administrator di awal.
        if (MenuRegistrar::register('finance', ['finance.legal-basis' => 'Legal Basis'], 40, 'page')) {
            MenuRegistrar::grantToAdminAndRoles(['finance.legal-basis'], [], ['create', 'edit', 'delete']);
        }
        $this->flush();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.legal-basis']);
        Schema::dropIfExists('legal_basis_register');
        $this->flush();
    }

    private function flush(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($id) {
            Cache::forget("perm_slugs_{$id}");
            Cache::forget("perm_matrix_{$id}");
        });
    }
};
