<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Aturan global sub-modul Cash Advance — SATU BARIS, dibaca lewat
 * CashAdvanceSetting::current() yang di-cache dan dibersihkan forgetCache().
 *
 * 🔴 SATU TABEL UNTUK CA DAN CAR. Kolom berawalan `car_` mengatur pelaporan,
 * sisanya mengatur pengajuan. Keduanya satu siklus bisnis dan selalu diatur
 * bersamaan; memecahnya jadi dua tabel setelan hanya menciptakan dua tempat
 * untuk mencari satu jawaban.
 *
 * ── HALAMAN SETTINGS ADALAH KATUP PENGAMAN MODUL (Keputusan D52) ────────────
 * Setiap aturan yang dapat MENOLAK pengajuan wajib bisa dilonggarkan dari sana
 * tanpa perubahan kode. Kebalikannya juga berlaku dan akan diaudit di A8:
 * setelan yang tersimpan, divalidasi, dan ditampilkan tetapi TIDAK PERNAH dibaca
 * satu baris kode pun adalah kegagalan — bukan fitur yang belum sempat dipakai.
 * Kegagalan itu sudah terjadi dua kali di repo ini (`require_location` yang tak
 * punya halaman, dan `self_approval_fallback_role_id` yang tak pernah dibaca).
 *
 * ── DUA KOLOM PENANDA TANGAN, BUKAN TIGA ───────────────────────────────────
 * Cetakan acuan punya empat kolom: Requester · Accounting · Cashier · Approved by.
 * Tiga yang pertama sumbernya jelas — pemohon dari dokumen, dua sisanya dari
 * tabel ini. Yang keempat SENGAJA TIDAK disimpan di sini: "Approved by" diambil
 * dari orang yang BENAR-BENAR menyetujui pada langkah ber-actor_role = approver.
 *
 * Ini penerapan langsung Keputusan D129. Menyimpan penanda tangan di dua tempat
 * hanya melahirkan satu kelas kesalahan baru: setelan berkata A, riwayat
 * persetujuan berkata B, dan tidak ada yang tahu mana yang benar — sementara
 * yang tercetak adalah kertas yang ditandatangani orang sungguhan.
 * `reimbursement_settings` punya `approver_signer_employee_id`; tabel ini TIDAK,
 * dan itu perbedaan yang disengaja.
 *
 * ── allow_future_date BAWAANNYA TRUE ───────────────────────────────────────
 * Kebalikan dari Reimbursement, sama dengan Purchase Request. CA meminta uang
 * untuk kegiatan yang BELUM terjadi, jadi tanggal di masa depan justru normal.
 * Reimbursement mengganti uang yang sudah keluar, sehingga di sana tanggal masa
 * depan memang janggal.
 *
 * ── require_cost_center BAWAANNYA FALSE ────────────────────────────────────
 * Form acuan tidak punya kolom pembebanan sama sekali. Kolomnya tetap dibuat
 * (lihat migrasi 000004) supaya menambahkannya kelak tidak menuntut ALTER TABLE
 * pada tabel dokumen keuangan, tetapi bawaannya mati agar tampilan awal sama
 * persis dengan acuan. Hari ini tabel `branches` juga masih KOSONG — mewajibkan
 * pembebanan sekarang berarti tidak ada satu pun pengajuan yang bisa lolos.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advance_settings', function (Blueprint $table) {
            $table->id();

            // ── Identitas yang tercetak di kepala dokumen ──────────────────
            $table->string('company_name', 150)->default('PT Eclectic Consulting');
            $table->boolean('use_branch_name_in_header')->default(true);

            // ── Tanggal ───────────────────────────────────────────────────
            $table->boolean('allow_future_date')->default(true);
            $table->unsignedSmallInteger('max_backdate_days')->default(0); // 0 = tanpa batas
            // Field "Date" kedua pada form acuan, bertanda OPTIONAL.
            $table->boolean('allow_date_range')->default(true);

            // ── Nominal ───────────────────────────────────────────────────
            $table->decimal('min_amount', 20, 2)->default(0);
            $table->decimal('max_amount', 20, 2)->default(0); // 0 = tanpa batas
            // flag  = lewat batas tetap diterima, ditandai di kolom `flags`
            // block = lewat batas ditolak validasi
            $table->string('over_limit_policy', 10)->default('flag');

            // ── Mata uang ─────────────────────────────────────────────────
            // CSV, bukan tabel master: isinya 1-3 nilai yang berubah beberapa
            // tahun sekali. Alasan yang sama dengan `allowed_units` di Purchase
            // Request (Keputusan D128).
            // 🔴 NOL konversi kurs — tidak ada tabel kurs di basis data ini.
            //    CAR menuntut mata uangnya sama dengan CA-nya.
            $table->string('allowed_currencies', 100)->default('IDR');
            $table->string('default_currency', 3)->default('IDR');

            // ── Bukti pendukung (field "Detail URL" pada acuan) ────────────
            // Tautan, bukan unggahan — pola `supporting_url` Reimbursement.
            $table->boolean('require_detail_url')->default(false);
            $table->string('detail_url_allowed_hosts', 255)
                  ->default('drive.google.com,docs.google.com');

            // ── Kelengkapan ───────────────────────────────────────────────
            $table->unsignedTinyInteger('require_description_min_chars')->default(5);

            // ── Pembebanan ────────────────────────────────────────────────
            $table->boolean('require_cost_center')->default(false);
            $table->string('allowed_cost_center_types', 50)->default('branch,project');

            // ── Persetujuan ───────────────────────────────────────────────
            $table->boolean('allow_self_approval')->default(true);
            // Dibaca CashAdvanceService::canAct() untuk MENYEBUT NAMA ROLE yang
            // harus dimintai tinjauan saat self-approval ditolak. Purchase
            // Request membuktikan kolom ini mudah jadi setelan mati bila tidak
            // sengaja disambungkan (temuan audit P8).
            $table->unsignedBigInteger('self_approval_fallback_role_id')->nullable();
            $table->boolean('allow_approver_adjust_amount')->default(false);

            // ── Pembatalan oleh pemohon ───────────────────────────────────
            $table->boolean('allow_requester_cancel')->default(true);

            // ── Periode terkunci ──────────────────────────────────────────
            // off | block_employee | block_all — mesinnya PeriodService
            $table->string('locked_period_policy', 20)->default('block_employee');

            // ── Penanda tangan cetakan (DUA, lihat docblock) ──────────────
            $table->unsignedBigInteger('accounting_signer_employee_id')->nullable();
            $table->unsignedBigInteger('cashier_signer_employee_id')->nullable();

            // ── Cash Advance Report ───────────────────────────────────────
            // Menahan CA baru selama masih ada CA yang belum dipertanggung-
            // jawabkan. Bawaannya mati supaya tidak mengunci pengguna pertama.
            $table->boolean('require_car_before_new_ca')->default(false);
            // 0 = tanpa tenggat. Bila diisi, CA lewat tenggat ditandai `flags`.
            $table->unsignedSmallInteger('car_due_days')->default(0);
            // Realisasi melebihi nominal CA: diterima (menghasilkan `claim`)
            // atau ditolak validasi.
            $table->boolean('car_allow_over_amount')->default(true);
            $table->boolean('car_require_receipt_url')->default(false);

            // ── Jejak ─────────────────────────────────────────────────────
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            $table->foreign('self_approval_fallback_role_id')
                  ->references('id')->on('employee_role')
                  ->nullOnDelete();

            $table->foreign('accounting_signer_employee_id')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('cashier_signer_employee_id')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();
        });

        // Satu baris bawaan. Tanpa ini setiap pembacaan setelan harus menangani
        // kemungkinan "belum ada baris", dan cabang itu tidak pernah teruji.
        DB::table('cash_advance_settings')->insert([
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advance_settings');
    }
};
