<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cetakan langkah persetujuan Purchase Request — INI KONFIGURASI, BUKAN RIWAYAT.
 *
 * Riwayat persetujuan tiap dokumen disimpan terpisah di
 * `purchase_request_approvals`, DISALIN dari tabel ini saat dokumen dibuat.
 * Pemisahan itu yang membuat pengaturan boleh diubah kapan saja tanpa merusak
 * dokumen yang sedang berjalan — sudah teruji dua kali, di Overtime dan
 * Reimbursement.
 *
 * 🔴 KENAPA TABEL SENDIRI, PADAHAL DUA TABEL SERUPA SUDAH ADA (Keputusan D102):
 * memakai `reimbursement_approval_steps` berarti Purchase Request bergantung pada
 * tabel dan model bernama `reimbursement_*`, dan setiap perbaikan mesin
 * reimbursement akan menyentuh dokumen pengadaan yang sedang berjalan. Kontrak
 * modul HR & General sejak awal bersifat ADITIF — tabel baru, bukan tabel
 * bersama. Yang dibayar: pengulangan struktur. Yang dibeli: nol risiko terhadap
 * modul yang sudah jadi.
 *
 * ── SATU KOLOM YANG TIDAK ADA DI DUA MODUL SEBELUMNYA ──────────────────────
 *
 * `requester_selectable` (Keputusan D126) menjawab hal yang tampak seperti dua
 * mekanisme berbeda pada aplikasi acuan: di sana ADA pengaturan Verificator &
 * Approver, DAN ADA dropdown Approver di halaman pemohon. Keduanya benar dan
 * tidak bertentangan — yang menyatukannya adalah satu kolom per langkah.
 *
 * Bila `requester_selectable = true`, form pengajuan menampilkan dropdown berisi
 * kandidat langkah itu, dan pemohon memilih satu nama. Bila false, penyetuju
 * sepenuhnya ditentukan konfigurasi — perilaku Overtime & Reimbursement.
 *
 * Susunan target pemilik sistem (dibentuk lewat Settings, bukan di sini):
 *   1  Team Approval    employee/role   requester_selectable = TRUE   ← dipilih pemohon
 *   2  Verification     role            false                         ← konfigurasi
 *   3  Final Approval   employee        false                         ← konfigurasi
 *
 * TIGA PENJAGAAN yang WAJIB ada di PurchaseRequestSettingController, karena tabel
 * tidak dapat menegakkannya sendiri:
 *   a. Langkah `requester_selectable` dengan NOL kandidat ditolak saat disimpan.
 *      Tanpa itu, dokumen baru lahir tanpa jalan keluar.
 *   b. Pilihan pemohon dibekukan ke baris approval dokumen (kolom
 *      `approver_employee_ids` di tabel riwayat diisi satu id), bukan disimpan
 *      sebagai preferensi — mengganti kandidat di sini tidak boleh mengubah
 *      penyetuju dokumen yang sedang menunggu.
 *   c. Langkah aktif terakhir tidak boleh dimatikan atau dihapus.
 *
 * `approver_type = 'direct_manager'` SENGAJA DIDAFTARKAN meski belum dapat
 * dijalankan: tabel `employee` tidak punya `reports_to_id`, dan
 * `employee_basic_data.direct_supervision` 100% NULL untuk seluruh karyawan
 * (pekerjaan tertunda T.2). Pilihannya dinonaktifkan di UI. Saat hierarki
 * tersedia nanti, mengaktifkannya tidak memerlukan migrasi apa pun (pola D3, D81).
 *
 * Kolom `module` dipertahankan mengikuti dua tabel sejenis. Bila kelak ada modul
 * KELIMA dan KEENAM, barulah pantas dibuat satu mesin persetujuan generik —
 * dengan contoh nyata di tangan, bukan tebakan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_request_approval_steps', function (Blueprint $table) {
            $table->id();

            $table->string('module', 30)->default('purchase_request');
            $table->unsignedTinyInteger('order_seq')->default(1);
            $table->string('name', 100);

            // role | employee | direct_manager
            $table->string('approver_type', 20)->default('role');

            // Dipakai bila approver_type = role.
            $table->unsignedBigInteger('approver_role_id')->nullable();

            // Dipakai bila approver_type = employee. Daftar employee_id.
            // Disimpan JSON, bukan tabel pivot, karena isinya selalu dibaca utuh
            // bersama barisnya dan tidak pernah di-query per elemen.
            $table->json('approver_employee_ids')->nullable();

            // Pemohon memilih satu nama dari kandidat langkah ini (D126).
            $table->boolean('requester_selectable')->default(false);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            $table->index(['module', 'order_seq'], 'pr_steps_module_order_idx');
        });

        // Seed SATU langkah bernama "Verification", tipe role, dipegang
        // EC Administrator (role id 1), requester_selectable = false.
        //
        // Kenapa hanya satu dan kenapa Administrator (pola D88): alurnya harus
        // dapat diuji ujung ke ujung sejak migrasi pertama, oleh satu orang, tanpa
        // konfigurasi tambahan. Langkah `requester_selectable` TIDAK di-seed
        // karena ia menuntut daftar kandidat yang hanya pemilik sistem yang tahu —
        // dan langkah selectable tanpa kandidat justru melanggar penjagaan (a)
        // di atas.
        //
        // Susunan 2-3 langkah dibentuk pemilik sistem lewat halaman Purchase
        // Request Settings setelah alur dasarnya terbukti, tanpa perubahan kode.
        DB::table('purchase_request_approval_steps')->insert([
            'module'               => 'purchase_request',
            'order_seq'            => 1,
            'name'                 => 'Verification',
            'approver_type'        => 'role',
            'approver_role_id'     => 1,
            'requester_selectable' => false,
            'is_active'            => true,
            'created_at'           => now(),
            'updated_at'           => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_approval_steps');
    }
};
