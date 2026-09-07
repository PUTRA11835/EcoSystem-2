<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Header pengajuan uang muka — dokumen keuangan pertama di modul HR & General
 * yang benar-benar MENGELUARKAN uang perusahaan.
 *
 * Tiga sub-modul sebelumnya sengaja tidak: Overtime menunda nominal (nol tabel
 * gaji di basis data ini), Reimbursement mengganti uang yang SUDAH keluar dari
 * kantong karyawan, Purchase Request tidak punya nominal sama sekali. Cash
 * Advance berbeda — uang keluar SEBELUM dibelanjakan, dan itulah yang membuat
 * Cash Advance Report wajib ada dan bukan pelengkap.
 *
 * ── SATU DESKRIPSI, SATU NOMINAL — bukan multi-item ────────────────────────
 * Form acuan punya satu Description dan satu Amount; cetakannya menampilkan
 * tabel satu baris dengan Total di bawahnya. Tidak ada tabel `cash_advance_items`
 * di sini, dan itu keputusan sadar, bukan kelalaian. Bila kelak dibutuhkan,
 * menambah tabel ANAK tidak menuntut ALTER TABLE pada tabel ini — jadi pintunya
 * tidak tertutup permanen (pertanyaan C3).
 *
 * ── LIMA KOLOM PENYELESAIAN MASUK SEKARANG, BUKAN SAAT CAR DIBANGUN ────────
 *
 *   settlement_status · reported_amount · outstanding_amount · settled_at
 *   car_count
 *
 * 🔴 Alasannya bukan kerapian, melainkan biaya. Ini tabel dokumen keuangan;
 * begitu ia berisi uang berjalan, ALTER TABLE di atasnya adalah operasi yang
 * tidak ingin dilakukan siapa pun pada sistem yang sudah live. Repo ini sudah
 * mengambil pelajaran yang sama secara sadar di Keputusan D133 — `converted_at`
 * dan `converted_by` disiapkan di `purchase_requests` sejak migrasi pertama
 * justru supaya Purchase Order kelak tidak menuntut migrasi. Kaitan CA→CAR jauh
 * lebih erat daripada PR→PO: CAR tidak punya alasan untuk ada tanpa CA.
 *
 * Kelimanya nullable / berbawaan netral dan NOL UI sampai blok CAR dikerjakan.
 *
 * ── STATUS LIMA NILAI ──────────────────────────────────────────────────────
 *   submitted · in_review · approved · rejected · cancelled
 * Mengikuti Purchase Request (D131): karyawan boleh membatalkan selama belum ada
 * penyetuju yang bertindak. Reimbursement sengaja hanya punya empat (D111) —
 * di sana uang sudah keluar dari kantong karyawan, jadi membatalkan berarti
 * merelakan uang sendiri.
 *
 * 🔴 `status` dan `settlement_status` adalah DUA SUMBU BERBEDA, jangan disatukan.
 * Yang pertama menjawab "apakah dokumennya disetujui"; yang kedua "apakah uangnya
 * sudah dipertanggungjawabkan". Sebuah CA yang `approved` + `unreported` adalah
 * keadaan yang sepenuhnya normal — dan justru keadaan itulah yang paling perlu
 * dilihat bagian keuangan.
 *
 * ── PEMBEBANAN (Charged To) ────────────────────────────────────────────────
 * Kolomnya ada, kontrolnya dirender, tetapi `require_cost_center` bawaannya
 * FALSE — form acuan memang tidak punya kolom ini, dan tabel `branches` hari ini
 * masih kosong. Lihat docblock migrasi 000002.
 *
 * FK employee memakai `employee.employee_id`, BUKAN `employee.id` — primary key
 * tabel `employee` di aplikasi ini bukan `id`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_advances', function (Blueprint $table) {
            $table->id();

            // Format acuan: 26/IX/CA/00001 — dua digit tahun / bulan Romawi /
            // kode / lima digit, urutan reset tiap bulan (pertanyaan C1).
            // Dibangkitkan dalam transaksi ber-lockForUpdate() (Keputusan D92);
            // indeks unik ini adalah jaring pengaman terakhirnya, bukan
            // penjagaan utamanya.
            $table->string('request_no', 30)->unique();

            // Pemohon. SELALU diisi dari session('user')['id'], tidak pernah
            // dari badan request.
            $table->unsignedBigInteger('employee_id');

            // Terisi HANYA bila admin memakai tombol "New CA" atas nama orang
            // lain. Null berarti karyawan mengajukan sendiri — dan pemisahan itu
            // yang membuat pertanyaan "siapa sebenarnya yang membuat dokumen ini"
            // punya jawaban.
            $table->unsignedBigInteger('created_by')->nullable();

            // Dua field "Date" pada acuan; yang kedua bertanda OPTIONAL dan
            // dipakai bila kegiatannya berlangsung dalam rentang tanggal.
            $table->date('request_date');
            $table->date('request_date_to')->nullable();

            // Field "Description" — menjadi SATU-SATUNYA baris tabel pada
            // cetakan, jadi panjangnya sengaja dibatasi agar muat satu baris.
            $table->string('description', 255);

            $table->string('currency', 3)->default('IDR');
            $table->decimal('amount', 20, 2);

            // Field "Detail URL" — tautan Drive, bukan unggahan. Host divalidasi
            // terhadap `detail_url_allowed_hosts` di setelan.
            $table->string('detail_url', 500)->nullable();

            // Field "Additional Notes".
            $table->text('notes')->nullable();

            // ── Pembebanan, opsional (lihat docblock) ──────────────────────
            $table->string('cost_center_type', 20)->nullable(); // branch | project
            $table->unsignedBigInteger('charged_branch_id')->nullable();
            $table->unsignedBigInteger('charged_project_id')->nullable();
            // 🔴 DIBEKUKAN saat submit: nama cabang bisa berubah dan proyek bisa
            // ditutup, sementara dokumen lama harus tetap terbaca (D105/D127).
            $table->string('charged_to_label', 200)->nullable();

            // submitted | in_review | approved | rejected | cancelled
            $table->string('status', 20)->default('submitted');
            $table->unsignedTinyInteger('current_step_order')->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->unsignedBigInteger('cancelled_by')->nullable();

            // Saat seluruh langkah persetujuan lulus.
            $table->timestamp('completed_at')->nullable();

            // ── Penyelesaian lewat CAR (lihat docblock) ────────────────────
            // unreported | reporting | settled
            $table->string('settlement_status', 20)->default('unreported');
            // Total realisasi dari CAR yang DISETUJUI. Null selama belum ada.
            $table->decimal('reported_amount', 20, 2)->nullable();
            // amount - reported_amount.
            // Positif  = sisa dikembalikan karyawan (refund)
            // Negatif  = kekurangan ditagihkan ke perusahaan (claim)
            $table->decimal('outstanding_amount', 20, 2)->nullable();
            $table->timestamp('settled_at')->nullable();
            // Berapa CAR menempel — kolom "CAR" di rekap tanpa query per baris.
            $table->unsignedTinyInteger('car_count')->default(0);

            // Penyaringan bulanan tanpa fungsi tanggal di WHERE (pola tiga
            // sub-modul sebelumnya).
            $table->unsignedSmallInteger('period_year');
            $table->unsignedTinyInteger('period_month');

            // Sinyal anomali: lewat batas nominal, lewat tenggat CAR, alur
            // diperluas saat dokumen berjalan (pola D10). Bentuknya bebas
            // karena jenis sinyalnya akan bertambah.
            $table->json('flags')->nullable();

            // Dokumen keuangan TIDAK boleh hilang tanpa jejak (Keputusan D109).
            $table->softDeletes();
            $table->unsignedBigInteger('deleted_by')->nullable();
            $table->string('delete_reason', 255)->nullable();

            $table->timestamps();

            $table->foreign('employee_id')
                  ->references('employee_id')->on('employee');

            $table->foreign('created_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('cancelled_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('deleted_by')
                  ->references('employee_id')->on('employee')
                  ->nullOnDelete();

            $table->foreign('charged_branch_id')
                  ->references('id')->on('branches')
                  ->nullOnDelete();

            $table->foreign('charged_project_id')
                  ->references('id')->on('delivery_projects')
                  ->nullOnDelete();

            $table->index(['employee_id', 'request_date'], 'ca_employee_date_idx');
            $table->index('status', 'ca_status_idx');
            $table->index('settlement_status', 'ca_settlement_idx');
            $table->index('request_date', 'ca_request_date_idx');
            $table->index(['period_year', 'period_month'], 'ca_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_advances');
    }
};
