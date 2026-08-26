<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi global sub-modul Overtime. Tabel satu baris.
 *
 * KENAPA TABEL SENDIRI, BUKAN MENUMPANG `attendance_settings`:
 * Aturannya milik domain berbeda dan berubah pada irama berbeda. Menumpangkan
 * akan membuat halaman Attendance Settings — yang sudah teruji dan dipakai —
 * ikut berubah setiap kali kebijakan lembur disesuaikan.
 *
 * KENAPA BANYAK KOLOM PENGATURAN:
 * Tujuh dari dua belas butir rancangan dijawab pemilik sistem dengan pola yang
 * sama: "tidak dibatasi, tapi sediakan konfigurasinya". Kebijakan semacam ini
 * berubah lebih sering daripada kodenya (preseden: Keputusan D54 pada batas
 * shift per karyawan). Bawaan seluruhnya dibuat PERMISIF — modul lahir longgar,
 * HR yang mengencangkan bila perlu.
 *
 * ANGKA 0 BERARTI "TANPA BATAS", bukan "nol menit". Dipilih agar HR tidak perlu
 * memahami arti NULL di layar; angka nol lebih mudah dibaca sebagai "tidak
 * dibatasi" daripada kotak kosong.
 *
 * Baris default dibuat di dalam migrasi ini karena aplikasi mengharapkan
 * barisnya selalu ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_settings', function (Blueprint $table) {
            $table->id();

            // ── Aturan tanggal ─────────────────────────────────────────────
            // Tanggal ke depan diizinkan atas permintaan eksplisit pemilik
            // sistem (Keputusan D75). Konsekuensinya ditangani di service:
            // pembanding absensi dilewati untuk tanggal yang belum terjadi,
            // supaya penanda "tidak ada catatan absensi" tidak selalu menyala
            // dan akhirnya diabaikan.
            $table->boolean('allow_future_date')->default(true);
            $table->unsignedSmallInteger('max_backdate_days')->default(0);   // 0 = tanpa batas

            // ── Aturan durasi ──────────────────────────────────────────────
            // Lintas tengah malam ditutup secara bawaan (Keputusan D83), tetapi
            // tetap dapat dibuka bila perusahaan mengubah ketentuannya.
            $table->boolean('allow_crosses_midnight')->default(false);
            $table->unsignedSmallInteger('min_duration_minutes')->default(0); // 0 = tanpa minimum
            $table->unsignedSmallInteger('max_daily_minutes')->default(0);    // 0 = tanpa maksimum
            $table->unsignedSmallInteger('max_weekly_minutes')->default(0);   // 0 = tanpa maksimum

            // ── Pembanding terhadap absensi ────────────────────────────────
            // Selisih di bawah ambang ini dianggap wajar. Di atasnya diberi
            // flag — DITANDAI, bukan ditolak (Keputusan D84 & D87).
            $table->unsignedSmallInteger('mismatch_tolerance_minutes')->default(60);

            $table->unsignedTinyInteger('require_reason_min_chars')->default(10);

            // ── Persetujuan ────────────────────────────────────────────────
            // allow_self_approval BAWAANNYA AKTIF atas keputusan pemilik sistem.
            // Catatan yang sengaja ditinggalkan di sini: selama alurnya masih
            // SATU langkah, pemegang role penyetuju dapat mengajukan sekaligus
            // menyelesaikan pengajuannya sendiri. Karena itu setiap kejadian
            // semacam ini WAJIB diberi flag `self_approved` dan ditampilkan
            // menonjol di rekap, supaya tetap terlihat meski diizinkan.
            $table->boolean('allow_self_approval')->default(true);
            $table->unsignedBigInteger('self_approval_fallback_role_id')->nullable();

            // Penyetuju boleh menyesuaikan jam yang diajukan. Nilai asli selalu
            // disalin ke original_* pada tabel pengajuan sehingga angka yang
            // semula diklaim karyawan tidak pernah hilang.
            $table->boolean('allow_approver_adjust_time')->default(true);

            // ── Periode terkunci ───────────────────────────────────────────
            // Dijadikan pilihan, bukan boolean, karena pemilik sistem meminta
            // aturannya dapat diatur bebas:
            //   off             : penguncian periode diabaikan
            //   block_employee  : karyawan tidak bisa, pemegang hak kelola bisa
            //   block_all       : siapa pun tidak bisa
            $table->string('locked_period_policy', 20)->default('block_employee');

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });

        DB::table('overtime_settings')->insert([
            'allow_future_date'            => true,
            'max_backdate_days'            => 0,
            'allow_crosses_midnight'       => false,
            'min_duration_minutes'         => 0,
            'max_daily_minutes'            => 0,
            'max_weekly_minutes'           => 0,
            'mismatch_tolerance_minutes'   => 60,
            'require_reason_min_chars'     => 10,
            'allow_self_approval'          => true,
            'allow_approver_adjust_time'   => true,
            'locked_period_policy'         => 'block_employee',
            'created_at'                   => now(),
            'updated_at'                   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('overtime_settings');
    }
};
