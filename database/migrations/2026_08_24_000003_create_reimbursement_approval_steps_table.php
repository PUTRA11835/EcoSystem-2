<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cetakan langkah persetujuan reimbursement — INI KONFIGURASI, BUKAN RIWAYAT.
 *
 * Riwayat persetujuan tiap pengajuan disimpan terpisah di
 * `reimbursement_request_approvals`, disalin dari tabel ini saat pengajuan
 * dibuat. Pemisahan itu yang membuat pengaturan boleh diubah kapan saja tanpa
 * merusak pengajuan yang sedang berjalan — sudah TERUJI di Overtime: langkah
 * dihapus di tengah jalan, pengajuan berjalan tetap utuh.
 *
 * 🔴 KENAPA TABEL SENDIRI, PADAHAL `overtime_approval_steps` SUDAH PUNYA KOLOM
 * `module` YANG MEMANG DISIAPKAN UNTUK DIPAKAI BERSAMA (Keputusan D102):
 *
 * Memakainya berarti Reimbursement bergantung pada tabel dan model bernama
 * `overtime_*`, dan setiap perbaikan mesin lembur akan menyentuh dokumen
 * keuangan yang sedang berjalan. Modul Overtime sudah selesai, teruji, dan
 * menuju produksi; kontrak modul HR & General sejak awal bersifat ADITIF —
 * tabel baru, bukan tabel bersama.
 *
 * Yang dibayar: dua tabel tambahan dan pengulangan sekitar 200 baris mesin
 * persetujuan. Yang dibeli: nol risiko terhadap modul yang sudah jadi.
 *
 * Kolom `module` tetap dipertahankan di sini. Bila kelak ada modul KETIGA dan
 * KEEMPAT (Cuti, Cash Advance), barulah pantas dibuat satu mesin persetujuan
 * generik — dengan tiga contoh nyata di tangan, bukan dua tebakan.
 *
 * `approver_type` = 'direct_manager' SENGAJA DIDAFTARKAN meski belum dapat
 * dijalankan: tabel `employee` tidak punya `reports_to_id`, dan
 * `employee_basic_data.direct_supervision` / `.manager` 100% NULL untuk seluruh
 * karyawan. Pilihannya dinonaktifkan di UI. Saat hierarki tersedia nanti,
 * mengaktifkannya tidak memerlukan migrasi apa pun (pola Keputusan D3 & D81).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_approval_steps', function (Blueprint $table) {
            $table->id();

            $table->string('module', 30)->default('reimbursement');
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

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            $table->index(['module', 'order_seq'], 'reimb_steps_module_order_idx');
        });

        // Seed satu langkah bernama "GA Verification", dipegang EC Administrator
        // (role id 1).
        //
        // Namanya mengikuti aplikasi acuan, yang menampilkan status
        // "Pending GA Verification" — sehingga label status di layar langsung
        // cocok tanpa penyesuaian.
        //
        // Role-nya sementara Administrator, sesuai jawaban R6, supaya alurnya
        // dapat diuji menyeluruh lebih dulu. Setelah teruji pemilik sistem
        // menggantinya ke HO GA Administrator (28) dan menambah langkah kedua
        // HO Finance Administrator (25) lewat halaman Reimbursement Settings —
        // tanpa perubahan kode sama sekali.
        DB::table('reimbursement_approval_steps')->insert([
            'module'           => 'reimbursement',
            'order_seq'        => 1,
            'name'             => 'GA Verification',
            'approver_type'    => 'role',
            'approver_role_id' => 1,
            'is_active'        => true,
            'created_at'       => now(),
            'updated_at'       => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_approval_steps');
    }
};
