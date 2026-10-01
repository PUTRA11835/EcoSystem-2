<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Profil HR karyawan — satu baris per karyawan (Tahap 3, langkah H3.1; HC-D20, HC-D26…D34).
 *
 * KENAPA TABEL 1:1 TERPISAH: `employee_basic_data` dibaca ± 130 berkas dan sudah menyimpang
 * antara jalur HR dan produksi. Atribut baru ditaruh di sini agar tabel lama TIDAK disentuh
 * sama sekali (tak ada kolom lama diubah/dihapus/dipindah — HC-D15: ESH hanya penambahan).
 *
 * BARIS DIBUAT SAAT PERTAMA DISIMPAN, tidak diisi massal untuk 210 karyawan → tak ada data lama
 * tersentuh. Semua kolom (selain kunci) boleh kosong.
 *
 * NIP tidak punya kolom sendiri: diputuskan NIP = ECI/No. Karyawan (HC-D28).
 *
 * KELOMPOK KOLOM
 *  1. Kepegawaian (HR saja): employment_status, grade_id, probation_end_date, hr_notes.
 *  2. Data pribadi (boleh diubah pemilik selama profil belum dikunci): golongan darah, nama ibu
 *     kandung, kontak darurat. Foto & tanda tangan: hanya PATH berkas di disk privat (bukan
 *     URL publik); unggah/unduh lewat rute berizin.
 *  3. 🔴 PAYROLL — NONAKTIF (HC-D21, lihat docs/humancapital/06-CATATAN-PAYROLL.md): ptkp_code,
 *     dependents_count, bpjs_*, payroll_activated. Data boleh disimpan, tetapi TIDAK dipakai
 *     perhitungan/layar apa pun sampai modul payroll ada dan saklar konfigurasi dinyalakan.
 *  4. Kunci profil (HC-D29): locked_at/locked_by. Terisi = pemilik tidak dapat mengubah seksi
 *     yang dinilai Onboarding; HR "Verify & Lock" → terisi, HR "Unlock" → kosong (alasan dan
 *     riwayat dicatat di `employee_history`, bukan di sini).
 *
 * DATA SENSITIF (kesehatan, pribadi pihak ketiga, gaji): tabel ini wajib didaftarkan ke
 * TableAccess (asisten AI) dan dijaga slug izin sendiri — dikerjakan di H3.4, sebelum tabel
 * dipakai layar mana pun. Selama itu tabel kosong dan tak terbaca kode apa pun.
 *
 * Hanya menambah tabel; down() menghapusnya. FK ke tabel lama hanya sebagai referensi
 * (cascade hapus milik karyawan itu sendiri; grade/pengubah → SET NULL agar hapus grade tak
 * menghapus profil).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_hr_profile', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('employee_id');

            // 1. Kepegawaian — nilai dibatasi di kode (EmploymentStatus), bukan ENUM DB,
            //    supaya daftar dapat bertambah tanpa migrasi.
            $table->string('employment_status', 20)->nullable();
            $table->unsignedBigInteger('grade_id')->nullable();
            $table->date('probation_end_date')->nullable();
            $table->text('hr_notes')->nullable();

            // 2. Data pribadi
            $table->string('blood_type', 3)->nullable();
            $table->string('mother_maiden_name', 150)->nullable();
            $table->string('emergency_contact_name', 150)->nullable();
            $table->string('emergency_contact_relation', 50)->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('photo_path', 255)->nullable();
            $table->string('signature_path', 255)->nullable();

            // 3. PAYROLL — NONAKTIF (HC-D21). Jangan dipakai perhitungan sebelum modul payroll ada.
            $table->string('ptkp_code', 10)->nullable();
            $table->unsignedTinyInteger('dependents_count')->nullable();
            $table->boolean('bpjs_health_active')->nullable();
            $table->boolean('bpjs_employment_active')->nullable();
            $table->unsignedTinyInteger('bpjs_dependents_count')->nullable();
            $table->boolean('payroll_activated')->default(false);

            // 4. Kunci profil (HC-D29)
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->unique('employee_id', 'uq_employee_hr_profile_employee');
            $table->index('employment_status', 'idx_employee_hr_profile_status');
            $table->index('probation_end_date', 'idx_employee_hr_profile_probation');
            $table->index('grade_id', 'idx_employee_hr_profile_grade');

            $table->foreign('employee_id', 'fk_employee_hr_profile_employee')
                ->references('employee_id')->on('employee')->cascadeOnDelete();
            $table->foreign('grade_id', 'fk_employee_hr_profile_grade')
                ->references('id')->on('grades')->nullOnDelete();
            $table->foreign('locked_by', 'fk_employee_hr_profile_locked_by')
                ->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('updated_by', 'fk_employee_hr_profile_updated_by')
                ->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_hr_profile');
    }
};
