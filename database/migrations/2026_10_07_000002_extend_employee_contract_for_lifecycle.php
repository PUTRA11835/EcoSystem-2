<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menu Contract (HC-D66): `employee_contract` diperluas — HANYA menambah kolom yang boleh kosong.
 *
 * Satu sumber kebenaran: tab Contract di Master Employee, kartu Onboarding "Active employment contract" dan
 * menu Contract membaca tabel yang sama. Kolom lama (contract_number, contract_type, is_active, ...) tidak diubah,
 * sehingga EmployeeContractController lama tetap berjalan apa adanya.
 *
 *   lifecycle_status   draft | active | terminated; NULL = baris lama. (Bukan `status`: model lama punya aksesor getStatusAttribute()
 *                      yang menghitung status dari tanggal dan akan menutupi kolom bernama `status`.) "Expired" tidak disimpan: dihitung dari end_date
 *                      (ContractRules::effectiveStatus). is_active tetap ikut diatur agar Onboarding tidak berubah.
 *   template_id        template yang dipakai saat dibuat (informasi; teks yang berlaku ada di body_html)
 *   signed_date        tanggal tanda tangan (muncul di kalimat pembuka dokumen)
 *   department,
 *   work_location      isian dokumen; kosong = mengikuti data karyawan
 *   salary_components  tunjangan sebagai cuplikan dokumen: [{"name": "...", "amount": 750000}] (gaji pokok = kolom `salary`)
 *   notes              catatan internal HR (juga placeholder {{catatan}} kontrak external)
 *   body_html          teks TEMPLATE yang dibekukan saat kontrak diaktifkan (masih memuat {{placeholder}}; nilainya diisi saat ditampilkan,
 *                      jadi perubahan isian ikut, sedangkan perubahan template tidak mengubah kontrak yang sudah berlaku)
 *   signatory_*        penandatangan perusahaan (dari Letter Templates → Settings → Signers), dibekukan sebagai nama + jabatan
 *   created_by         employee_id pembuat
 */
return new class extends Migration
{
    private const COLUMNS = [
        'lifecycle_status', 'template_id', 'signed_date', 'department', 'work_location',
        'salary_components', 'notes', 'body_html', 'created_by',
        'signatory_employee_id', 'signatory_name', 'signatory_title',
    ];

    public function up(): void
    {
        Schema::table('employee_contract', function (Blueprint $table) {
            if (!Schema::hasColumn('employee_contract', 'lifecycle_status')) {
                $table->string('lifecycle_status', 20)->nullable()->after('is_active');
            }
            if (!Schema::hasColumn('employee_contract', 'template_id')) {
                $table->unsignedBigInteger('template_id')->nullable()->after('lifecycle_status');
            }
            if (!Schema::hasColumn('employee_contract', 'signed_date')) {
                $table->date('signed_date')->nullable()->after('end_date');
            }
            if (!Schema::hasColumn('employee_contract', 'department')) {
                $table->string('department', 255)->nullable()->after('position');
            }
            if (!Schema::hasColumn('employee_contract', 'work_location')) {
                $table->string('work_location', 255)->nullable()->after('department');
            }
            if (!Schema::hasColumn('employee_contract', 'salary_components')) {
                $table->json('salary_components')->nullable()->after('salary');
            }
            if (!Schema::hasColumn('employee_contract', 'notes')) {
                $table->text('notes')->nullable();
            }
            if (!Schema::hasColumn('employee_contract', 'body_html')) {
                $table->longText('body_html')->nullable();
            }
            if (!Schema::hasColumn('employee_contract', 'created_by')) {
                $table->unsignedBigInteger('created_by')->nullable();
            }
            if (!Schema::hasColumn('employee_contract', 'signatory_employee_id')) {
                $table->unsignedBigInteger('signatory_employee_id')->nullable();
                $table->string('signatory_name', 150)->nullable();
                $table->string('signatory_title', 150)->nullable();
            }
        });

        Schema::table('employee_contract', function (Blueprint $table) {
            $table->index('lifecycle_status', 'employee_contract_lifecycle_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('employee_contract', function (Blueprint $table) {
            $table->dropIndex('employee_contract_lifecycle_status_index');
        });

        Schema::table('employee_contract', function (Blueprint $table) {
            $present = array_values(array_filter(self::COLUMNS, fn ($c) => Schema::hasColumn('employee_contract', $c)));
            if ($present) {
                $table->dropColumn($present);
            }
        });
    }
};
