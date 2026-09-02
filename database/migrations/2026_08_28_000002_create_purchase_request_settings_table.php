<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan global sub-modul Purchase Request — SATU BARIS, tidak pernah lebih.
 *
 * Dibaca lewat PurchaseRequestSetting::current() + forgetCache(), meniru
 * OvertimeSetting dan ReimbursementSetting.
 *
 * Halaman Settings adalah KATUP PENGAMAN modul (pelajaran Keputusan D52): setiap
 * aturan yang dapat MENOLAK pengajuan wajib dapat dilonggarkan dari sana tanpa
 * perubahan kode. Karena itu setiap kolom di bawah punya pasangan pemeriksaan di
 * PurchaseRequestService — setelan yang tidak berpengaruh adalah kegagalan, dan
 * akan diaudit di langkah P8.
 *
 * ── TIGA PERBEDAAN YANG DISENGAJA DARI `reimbursement_settings` ─────────────
 *
 * 1. `allow_future_date` BAWAANNYA true, bukan false.
 *    Reimbursement mengganti uang yang SUDAH keluar, jadi tanggal masa depan
 *    janggal. Purchase Request meminta barang yang BELUM dibeli, jadi tanggal
 *    masa depan justru pemakaian yang paling wajar. Menyalin bawaan Reimbursement
 *    mentah-mentah akan memblokir kasus utamanya sendiri.
 *
 * 2. NOL kolom penanda tangan (Keputusan D129).
 *    Rancangan awal menyiapkan `verifier_signer_employee_id` dan
 *    `approver_signer_employee_id`. Keduanya dibatalkan karena blok tanda tangan
 *    pada cetakan diturunkan dari LANGKAH ALUR: satu kolom per langkah aktif,
 *    diisi nama orang yang benar-benar bertindak. Menyimpan penanda tangan di dua
 *    tempat hanya menciptakan satu kelas kesalahan baru — setelan berkata A,
 *    riwayat persetujuan berkata B, dan tidak ada yang tahu mana yang benar.
 *    Reimbursement TETAP memerlukannya karena Accounting dan Kasir di sana bukan
 *    bagian dari alur persetujuan, sehingga tidak dapat diturunkan dari mana pun.
 *
 * 3. NOL kolom nominal.
 *    Tidak ada `max_request_amount`, `min_item_amount`, maupun
 *    `over_limit_policy`: harga baru muncul di Purchase Order. Yang dibatasi di
 *    sini adalah KUANTITAS (`max_qty_per_item`), bukan rupiah.
 *
 * ── Catatan kolom ──────────────────────────────────────────────────────────
 *
 *  - `allowed_units` disimpan sebagai CSV, bukan tabel master (Keputusan D128).
 *    Nilainya tetap DATA yang dapat diubah pemilik sistem — tuntutan D57
 *    terpenuhi — hanya wadahnya lebih ringan. Tabel master baru pantas dibuat
 *    bila satuan punya atribut (faktor konversi, aktif/nonaktif); hari ini tidak
 *    ada satu pun. Naik ke tabel master kelak murah: kolom `unit` sudah varchar
 *    dan isinya sudah divalidasi.
 *
 *  - `allowed_cost_center_types` adalah katup pengaman untuk Keputusan D127.
 *    Pembebanan ke proyek dinyalakan sejak awal karena datanya memang ada (22
 *    dari 23 `delivery_projects` belum ditutup). Bila kelak ternyata hanya cabang
 *    yang dipakai, buang `project` dari CSV ini — nol perubahan kode.
 *
 *  - `allow_requester_cancel` menemani Keputusan D131. Karyawan boleh
 *    membatalkan PR-nya sendiri selama status masih `submitted`; sakelar ini ada
 *    supaya perilaku itu dapat dimatikan tanpa menyentuh kode, sesuai D52.
 *
 *  - `max_qty_per_item` bertipe decimal, bukan integer, karena `qty` sendiri
 *    decimal — 0,5 LOT adalah permintaan yang wajar.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_settings', function (Blueprint $table) {
            $table->id();

            // ── Identitas cetakan ──────────────────────────────────────────
            $table->string('company_name', 150)->default('PT Eclectic Consulting');
            $table->boolean('use_branch_name_in_header')->default(true);

            // ── Aturan tanggal ─────────────────────────────────────────────
            $table->boolean('allow_future_date')->default(true);          // ← beda dari RB
            $table->unsignedSmallInteger('max_backdate_days')->default(0); // 0 = tanpa batas
            $table->boolean('require_use_date')->default(false);
            $table->boolean('require_period')->default(false);

            // ── Aturan baris item ──────────────────────────────────────────
            $table->unsignedTinyInteger('max_items_per_request')->default(0); // 0 = tanpa batas
            $table->decimal('max_qty_per_item', 12, 2)->default(0);           // 0 = tanpa batas
            $table->string('allowed_units', 255)->default('PC,UNIT,SET,BOX,LOT');
            $table->string('default_unit', 20)->default('PC');
            $table->boolean('require_cost_center_per_item')->default(true);
            $table->string('allowed_cost_center_types', 50)->default('branch,project');

            // ── Kelengkapan dokumen ────────────────────────────────────────
            $table->unsignedTinyInteger('require_title_min_chars')->default(5);

            // ── Persetujuan ────────────────────────────────────────────────
            $table->boolean('allow_self_approval')->default(true);
            $table->unsignedBigInteger('self_approval_fallback_role_id')->nullable();
            $table->boolean('allow_approver_adjust_items')->default(false);

            // ── Pembatalan oleh pemohon (D131) ─────────────────────────────
            $table->boolean('allow_requester_cancel')->default(true);

            // ── Periode terkunci ───────────────────────────────────────────
            $table->string('locked_period_policy', 20)->default('block_employee');

            // ── Jejak ──────────────────────────────────────────────────────
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('self_approval_fallback_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            // PK tabel employee adalah employee_id, BUKAN id.
            $table->foreign('updated_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();
        });

        // Satu baris bawaan. Tanpa baris ini, halaman Settings terbuka kosong dan
        // seluruh pemeriksaan aturan harus menangani null di mana-mana.
        DB::table('purchase_request_settings')->insert([
            'company_name'                 => 'PT Eclectic Consulting',
            'use_branch_name_in_header'    => true,
            'allow_future_date'            => true,
            'max_backdate_days'            => 0,
            'require_use_date'             => false,
            'require_period'               => false,
            'max_items_per_request'        => 0,
            'max_qty_per_item'             => 0,
            'allowed_units'                => 'PC,UNIT,SET,BOX,LOT',
            'default_unit'                 => 'PC',
            'require_cost_center_per_item' => true,
            'allowed_cost_center_types'    => 'branch,project',
            'require_title_min_chars'      => 5,
            'allow_self_approval'          => true,
            'allow_approver_adjust_items'  => false,
            'allow_requester_cancel'       => true,
            'locked_period_policy'         => 'block_employee',
            'created_at'                   => now(),
            'updated_at'                   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_settings');
    }
};
