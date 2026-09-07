<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cetakan langkah persetujuan Cash Advance DAN Cash Advance Report —
 * INI KONFIGURASI, BUKAN RIWAYAT.
 *
 * Riwayat tiap dokumen disimpan terpisah di `cash_advance_approvals` dan
 * `cash_advance_report_approvals`, DISALIN dari tabel ini saat dokumen dibuat.
 * Pemisahan itu yang membuat pengaturan boleh diubah kapan saja tanpa merusak
 * dokumen yang sedang berjalan — sudah teruji tiga kali: Overtime, Reimbursement,
 * dan Purchase Request.
 *
 * ── SATU TABEL, DUA MODUL — dan kenapa kali ini berbeda dari D102 ───────────
 *
 * Tiga tabel langkah sebelumnya masing-masing berdiri sendiri (D102): memakai
 * tabel milik modul lain berarti setiap perbaikan mesin modul itu menyentuh
 * dokumen modul ini. Alasan itu MASIH BERLAKU — dan justru itulah sebabnya CA
 * dan CAR boleh berbagi tabel: keduanya BUKAN modul lain. Keduanya satu
 * sub-modul, satu service, satu halaman setelan, satu siklus hidup dokumen, dan
 * dikerjakan dalam satu blok pekerjaan. Perbaikan pada salah satunya memang
 * SEHARUSNYA menyentuh keduanya.
 *
 * Kolom `module` sudah menjadi konvensi di tiga tabel sejenis, tetapi di sana ia
 * selalu berisi satu nilai yang dipatok. Di sini ia akhirnya dipakai sebagaimana
 * mestinya. Konsekuensinya harus dipegang disiplin:
 *
 *   🔴 SETIAP kueri ke tabel ini WAJIB menyaring `module`. Scope model
 *      forModule() dibuat di A2 supaya lupa-menyaring menjadi sulit, bukan
 *      sekadar terlarang. Unique-nya (module, order_seq) — BUKAN order_seq saja.
 *
 * ── SATU KOLOM YANG TIDAK ADA DI TIGA MODUL SEBELUMNYA: actor_role ──────────
 *
 * Editor alur pada aplikasi acuan punya EMPAT kontrol per langkah:
 *
 *   STEP NAME     "Verification" / "Position Approval"   -> name
 *   APPROVER TYPE "Direct User" / "By Position"          -> approver_type
 *   ACTOR         "Verificator" / "Approver"             -> actor_role  (BARU)
 *   REFERENCE     tag user:134 (multi) / "Director"      -> approver_employee_ids
 *                                                          / approver_role_id
 *
 * ACTOR bukan hiasan. Ia mengerjakan dua hal yang keduanya terlihat di layar:
 *
 *   1. Approval Timeline menuliskan "Verificator - Verification" dan
 *      "Approver - Position Approval". Bagian kiri itu actor_role, kanan name.
 *   2. Kolom "Approved by," pada CETAKAN mengambil pelaku langkah
 *      ber-actor_role = approver yang TERAKHIR menyetujui.
 *
 * 🔴 Kenapa tidak ditebak dari urutan saja ("langkah terakhir pasti approver"):
 * tebakan itu langsung salah begitu ada dua verifikator berturutan, atau begitu
 * alurnya dibalik lewat tombol naik/turun yang memang disediakan editor. Dan
 * salahnya tidak muncul sebagai galat — ia muncul di KERTAS YANG SUDAH
 * DITANDATANGANI, dengan nama orang yang keliru di kolom persetujuan.
 *
 * ── requester_selectable: menyalin maksud acuan, bukan cacatnya ─────────────
 *
 * Pada acuan, form ESS menolak submit dengan pesan
 *   "The approver_employee_id field must only contain digits and must be
 *    greater than zero."
 * padahal TIDAK ADA satu pun kontrol Approver di form itu. Server menuntut
 * sesuatu yang layarnya tidak pernah minta; pengguna tidak punya cara untuk
 * berhasil.
 *
 * Kolom ini (Keputusan D126, sudah teruji di Purchase Request) menutup celah itu:
 * bila true, form merender dropdown kandidat langkah tersebut dan pemohon memilih
 * satu nama; bila false, field-nya TIDAK dikirim dan TIDAK divalidasi.
 *
 * TIGA PENJAGAAN yang WAJIB ada di CashAdvanceSettingController, karena tabel
 * tidak dapat menegakkannya sendiri:
 *   a. Tiap modul WAJIB punya minimal satu langkah aktif ber-actor_role
 *      = approver. Tanpa itu kolom "Approved by" pada cetakan tidak punya sumber.
 *   b. Langkah requester_selectable dengan NOL kandidat ditolak saat disimpan —
 *      tanpa itu, dokumen baru lahir tanpa jalan keluar.
 *   c. Langkah aktif terakhir sebuah modul tidak boleh dimatikan atau dihapus.
 *
 * `approver_type = 'direct_manager'` SENGAJA DIDAFTARKAN meski belum dapat
 * dijalankan: `employee` tidak punya `reports_to_id` dan
 * `employee_basic_data.direct_supervision` 100% NULL (pekerjaan tertunda T.2).
 * Keterangan pada layar acuan — "For direct manager type, this reference may be
 * left empty" — sudah kita antisipasi: referensinya memang boleh kosong. Yang
 * berbeda, penyimpanannya DITOLAK hari ini dengan pesan yang menyebut alasannya,
 * bukan diterima lalu diam-diam tidak pernah menemukan penyetuju. Saat hierarki
 * tersedia nanti, mengaktifkannya tidak memerlukan migrasi apa pun (pola D3, D81).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_approval_steps', function (Blueprint $table) {
            $table->id();

            // cash_advance | cash_advance_report
            $table->string('module', 30)->default('cash_advance');
            $table->unsignedTinyInteger('order_seq')->default(1);
            $table->string('name', 100);

            // role | employee | direct_manager
            // Padanan acuan: By Position | Direct User | Direct Manager
            $table->string('approver_type', 20)->default('role');

            // Dipakai bila approver_type = role.
            $table->unsignedBigInteger('approver_role_id')->nullable();

            // Dipakai bila approver_type = employee. Daftar employee_id.
            // Disimpan JSON, bukan tabel pivot, karena isinya selalu dibaca utuh
            // bersama barisnya dan tidak pernah di-query per elemen.
            $table->json('approver_employee_ids')->nullable();

            // verificator | approver  — lihat docblock, kolom ini menentukan
            // label timeline DAN isi kolom "Approved by" pada cetakan.
            $table->string('actor_role', 20)->default('approver');

            // Pemohon memilih satu nama dari kandidat langkah ini (D126).
            $table->boolean('requester_selectable')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            // 🔴 UNIQUE PER MODUL, bukan per tabel. Satu tabel melayani dua alur,
            // dan keduanya punya langkah nomor 1.
            $table->unique(['module', 'order_seq'], 'ca_steps_module_order_unq');
            $table->index(['module', 'is_active'], 'ca_steps_module_active_idx');
        });

        // ── SEED ───────────────────────────────────────────────────────────
        //
        // Pola D88: alurnya harus dapat diuji ujung ke ujung sejak migrasi
        // pertama, oleh satu orang, tanpa konfigurasi tambahan. Karena itu
        // seluruh langkah dipegang EC Administrator (role id 1); role sungguhan
        // dipasang pemilik sistem lewat Settings, tanpa perubahan kode.
        //
        // Susunannya meniru tangkapan layar acuan: Verification lalu Position
        // Approval. Yang BERBEDA dari acuan — dan disengaja — langkah pertama
        // ditandai requester_selectable, sehingga dropdown Approver benar-benar
        // dirender di form. Itulah perbaikan atas cacat yang docblock ini sebut
        // di atas, dan ia sudah aktif sejak baris pertama, bukan menunggu
        // dikonfigurasi.
        //
        // 🔴 Ditulis SATU PER SATU, bukan insert massal: Laravel menyusun SQL
        // dari kunci baris PERTAMA, sehingga baris dengan himpunan kunci berbeda
        // ditolak "Column count doesn't match" (jebakan yang sudah terbukti di P1).
        DB::table('cash_advance_approval_steps')->insert([
            'module'               => 'cash_advance',
            'order_seq'            => 1,
            'name'                 => 'Verification',
            'approver_type'        => 'role',
            'approver_role_id'     => 1,
            'actor_role'           => 'verificator',
            'requester_selectable' => true,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        DB::table('cash_advance_approval_steps')->insert([
            'module'               => 'cash_advance',
            'order_seq'            => 2,
            'name'                 => 'Position Approval',
            'approver_type'        => 'role',
            'approver_role_id'     => 1,
            'actor_role'           => 'approver',
            'requester_selectable' => false,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);

        // CAR cukup satu langkah, dan langkah itu HARUS ber-actor_role = approver
        // supaya penjagaan (a) terpenuhi sejak awal dan cetakan CAR punya sumber
        // untuk kolom "Approved by".
        DB::table('cash_advance_approval_steps')->insert([
            'module'               => 'cash_advance_report',
            'order_seq'            => 1,
            'name'                 => 'Verification',
            'approver_type'        => 'role',
            'approver_role_id'     => 1,
            'actor_role'           => 'approver',
            'requester_selectable' => false,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_approval_steps');
    }
};
