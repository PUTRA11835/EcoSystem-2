<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Konfigurasi global sub-modul Reimbursement. Tabel satu baris.
 *
 * KENAPA TABEL SENDIRI, BUKAN MENUMPANG `overtime_settings`:
 * Alasan yang sama dengan mengapa `overtime_settings` tidak menumpang
 * `attendance_settings` — aturannya milik domain berbeda dan berubah pada irama
 * berbeda. Menumpangkan akan membuat halaman Overtime Settings yang sudah teruji
 * ikut berubah setiap kali kebijakan reimbursement disesuaikan.
 *
 * ANGKA 0 BERARTI "TANPA BATAS", bukan "nol". Konsisten dengan seluruh kolom
 * batas di `overtime_settings`, dan dipilih agar HR tidak perlu memahami arti
 * NULL di layar.
 *
 * Bawaan seluruhnya dibuat PERMISIF, kecuali dua hal yang menyangkut kelengkapan
 * bukti (`require_supporting_url`, `require_receipt_no`). Modul lahir longgar,
 * HR yang mengencangkan — tetapi dokumen tanpa bukti tidak dapat diaudit sama
 * sekali, jadi dua kolom itu lahir dalam keadaan menuntut.
 *
 * Catatan kolom yang perlu penjelasan:
 *
 *  - `over_limit_policy` (Keputusan D107). Dibuat PILIHAN, bukan boolean, karena
 *    pemilik sistem menolak perilaku setengah-setengah: "tandai gitu, reject ya
 *    reject". Dua mode yang tidak tumpang tindih:
 *      flag  : pengajuan DITERIMA, diberi flag, barisnya disorot di rekap
 *      block : pengajuan DITOLAK saat submit
 *    Bawaannya `flag`, dan itu bukan selera: batas keras pada nominal mendorong
 *    orang MEMECAH satu nota menjadi beberapa pengajuan agar lolos — justru
 *    menghapus sinyal yang ingin dilihat HR. Bentuk pilihan-bermode ini meniru
 *    `overtime_settings.locked_period_policy` (Keputusan D91).
 *
 *  - `supporting_url_allowed_hosts` (Keputusan D106). Bukti berupa TAUTAN, dan
 *    hari ini selalu Google Drive. Daftar host-nya dibuat dapat diatur, bukan
 *    dipatok di kode, karena bila perusahaan pindah ke OneDrive atau SharePoint
 *    itu harus jadi satu isian di halaman Settings — bukan permintaan perubahan
 *    kode.
 *
 *  - Tiga kolom penanda tangan menyimpan `employee_id`, BUKAN teks nama
 *    (Keputusan D108). Fitur gambar tanda tangan di profil karyawan sudah
 *    direncanakan; dengan id tersimpan, cetakan dan Excel nanti tinggal merender
 *    gambarnya di atas nama yang sudah ada — NOL migrasi di modul ini. Bila yang
 *    disimpan teks nama, penyambungannya nanti berarti mengubah tabel dan
 *    menebak-nebak siapa orangnya.
 *    `approver_signer_employee_id` boleh kosong: bila kosong, kolom "Approved by"
 *    pada cetakan jatuh ke penyetuju terakhir yang benar-benar menyetujui.
 *
 *  - `company_name` + `use_branch_name_in_header` (Keputusan D113) mengisi baris
 *    identitas perusahaan pada cetakan dan Excel. Bila seluruh item satu cabang
 *    dan penanda ini aktif, baris itu memakai nama cabang tersebut.
 *
 * Baris default dibuat di dalam migrasi ini karena aplikasi mengharapkan
 * barisnya selalu ada.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reimbursement_settings', function (Blueprint $table) {
            $table->id();

            // ── Identitas pada cetakan & Excel ─────────────────────────────
            $table->string('company_name', 150)->default('PT Eclectic Consulting');
            $table->boolean('use_branch_name_in_header')->default(true);

            // ── Aturan tanggal ─────────────────────────────────────────────
            // Berbeda dari Overtime: biaya reimbursement selalu SUDAH terjadi,
            // jadi tanggal ke depan ditutup secara bawaan. Tetap dapat dibuka
            // bila ternyata ada praktik pengajuan di muka.
            $table->boolean('allow_future_date')->default(false);
            $table->unsignedSmallInteger('max_backdate_days')->default(0);   // 0 = tanpa batas

            // ── Aturan item ────────────────────────────────────────────────
            $table->unsignedTinyInteger('max_items_per_request')->default(0); // 0 = tanpa batas
            $table->decimal('min_item_amount', 20, 2)->default(0);            // 0 = tanpa minimum

            // ── Aturan nominal ─────────────────────────────────────────────
            $table->decimal('max_request_amount', 20, 2)->default(0);         // 0 = tanpa batas
            $table->string('over_limit_policy', 10)->default('flag');         // flag|block

            // ── Kelengkapan dokumen ────────────────────────────────────────
            $table->unsignedTinyInteger('require_title_min_chars')->default(5);
            $table->boolean('require_supporting_url')->default(true);
            $table->string('supporting_url_allowed_hosts', 255)
                  ->default('drive.google.com,docs.google.com');
            $table->boolean('require_receipt_no')->default(true);

            // ── Persetujuan ────────────────────────────────────────────────
            // Menyamai Overtime: bawaannya aktif, tetapi setiap kejadiannya WAJIB
            // diberi flag `self_approved` dan ditampilkan menonjol di rekap,
            // supaya tetap terlihat meski diizinkan (Keputusan D89).
            $table->boolean('allow_self_approval')->default(true);
            $table->unsignedBigInteger('self_approval_fallback_role_id')->nullable();

            // Penyesuaian NOMINAL oleh penyetuju ditutup secara bawaan. Berbeda
            // sikapnya dari penyesuaian JAM pada Overtime: jam yang salah tulis
            // masih dapat ditaksir dari catatan absensi, sementara nominal hanya
            // dapat diverifikasi dari nota — dan notanya milik pemohon.
            $table->boolean('allow_approver_adjust_amount')->default(false);

            // ── Periode terkunci ───────────────────────────────────────────
            //   off             : penguncian periode diabaikan
            //   block_employee  : karyawan tidak bisa, pemegang hak kelola bisa
            //   block_all       : siapa pun tidak bisa
            $table->string('locked_period_policy', 20)->default('block_employee');

            // ── Penanda tangan pada cetakan ────────────────────────────────
            $table->unsignedBigInteger('accounting_signer_employee_id')->nullable();
            $table->unsignedBigInteger('cashier_signer_employee_id')->nullable();
            $table->unsignedBigInteger('approver_signer_employee_id')->nullable();

            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();

            // PK tabel employee adalah employee_id, BUKAN id.
            $table->foreign('accounting_signer_employee_id')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('cashier_signer_employee_id')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('approver_signer_employee_id')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();
        });

        DB::table('reimbursement_settings')->insert([
            'company_name'                 => 'PT Eclectic Consulting',
            'use_branch_name_in_header'    => true,
            'allow_future_date'            => false,
            'max_backdate_days'            => 0,
            'max_items_per_request'        => 0,
            'min_item_amount'              => 0,
            'max_request_amount'           => 0,
            'over_limit_policy'            => 'flag',
            'require_title_min_chars'      => 5,
            'require_supporting_url'       => true,
            'supporting_url_allowed_hosts' => 'drive.google.com,docs.google.com',
            'require_receipt_no'           => true,
            'allow_self_approval'          => true,
            'allow_approver_adjust_amount' => false,
            'locked_period_policy'         => 'block_employee',
            'created_at'                   => now(),
            'updated_at'                   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('reimbursement_settings');
    }
};
