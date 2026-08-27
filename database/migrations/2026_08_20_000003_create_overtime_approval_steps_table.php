<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Cetakan langkah persetujuan lembur — INI KONFIGURASI, BUKAN RIWAYAT.
 *
 * Riwayat persetujuan tiap pengajuan disimpan terpisah di
 * `overtime_request_approvals`, disalin dari tabel ini saat pengajuan dibuat.
 * Pemisahan itu yang membuat pengaturan boleh diubah kapan saja tanpa merusak
 * pengajuan yang sedang berjalan.
 *
 * KENAPA MESINNYA DIBANGUN PENUH SEJAK AWAL, PADAHAL BARU SATU LANGKAH:
 * Alternatifnya adalah membuat halaman pengaturan sebagai "gambaran" yang belum
 * tersambung ke mesin, lalu menyambungkannya nanti. Itu TIDAK lebih hemat —
 * tabel dan halamannya tetap harus dibuat, dan yang tersisa hanyalah menelusuri
 * daftar langkah, yang tidak lebih sulit daripada memeriksa satu role secara
 * langsung. Sementara biaya penyambungan ulang jatuh persis ketika sistem sudah
 * berisi data sungguhan. Lebih buruk lagi, halaman pengaturan yang nilainya
 * diabaikan mesin lebih menyesatkan daripada tidak ada halaman sama sekali —
 * pemakainya mengira sudah mengubah sesuatu (pelajaran Keputusan D52).
 *
 * KOLOM `module`:
 * Diisi 'overtime' untuk seluruh baris saat ini. Ada sejak awal supaya modul
 * lain (mis. Cuti) dapat ikut memakai mesin yang sama tanpa migrasi ubah-tabel.
 *
 * `approver_type` = 'direct_manager' SENGAJA DIDAFTARKAN meski belum dapat
 * dijalankan: tabel `employee` tidak punya `reports_to_id`, dan
 * `employee_basic_data.direct_supervision` / `.manager` 100% NULL untuk seluruh
 * karyawan. Pilihannya dinonaktifkan di UI. Saat hierarki tersedia nanti,
 * mengaktifkannya tidak memerlukan migrasi apa pun — pola yang sama dengan
 * `attendance_corrections.approved_by_type` (Keputusan D3 & D81).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_approval_steps', function (Blueprint $table) {
            $table->id();

            $table->string('module', 30)->default('overtime');
            $table->unsignedTinyInteger('order_seq')->default(1);
            $table->string('name', 100);

            // role | employee | direct_manager
            $table->string('approver_type', 20)->default('role');

            // Dipakai bila approver_type = role.
            $table->unsignedBigInteger('approver_role_id')->nullable();

            // Dipakai bila approver_type = employee. Daftar employee_id.
            // Disimpan JSON, bukan tabel pivot, karena isinya selalu dibaca
            // utuh bersama barisnya dan tidak pernah di-query per elemen.
            $table->json('approver_employee_ids')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->foreign('approver_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            $table->index(['module', 'order_seq'], 'overtime_steps_module_order_idx');
        });

        // Seed satu langkah, dipegang EC Administrator (role id 1).
        //
        // Sementara ini sengaja Administrator supaya alurnya dapat diuji
        // menyeluruh lebih dulu. Setelah teruji, pemilik sistem mengganti
        // role-nya — dan menambah langkah kedua — lewat halaman
        // HR & General -> Overtime Settings, tanpa perubahan kode sama sekali.
        DB::table('overtime_approval_steps')->insert([
            'module'         => 'overtime',
            'order_seq'      => 1,
            'name'           => 'Admin Approval',
            'approver_type'  => 'role',
            'approver_role_id' => 1,
            'is_active'      => true,
            'created_at'     => now(),
            'updated_at'     => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_approval_steps');
    }
};
