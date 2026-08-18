<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Team Members boleh berisi orang VENDOR yang tidak terdaftar di master employee.
 *
 * Konsekuensinya:
 *  - `employee_id` jadi NULLABLE (baris vendor tidak punya employee).
 *  - `vendor_id`   FK ke master Business Partner bertipe Vendor (tabel `customer`).
 *  - `member_name` / `member_position` menyimpan identitas orang vendor tersebut
 *    (free text) — untuk baris employee dua kolom ini tetap NULL dan namanya
 *    diambil dari employee_basic_data seperti sebelumnya.
 *
 * Kolom `vendor_name` yang lama TETAP diisi (nama vendor saat penugasan dibuat)
 * supaya seluruh pembaca lama (Reporting, API) tidak perlu ikut berubah.
 */
return new class extends Migration
{
    public function up(): void
    {
        // FK lama harus dilepas dulu sebelum kolomnya diubah jadi nullable.
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->unsignedBigInteger('employee_id')->nullable()->change();

            if (!Schema::hasColumn('delivery_project_employee', 'vendor_id')) {
                $table->unsignedBigInteger('vendor_id')->nullable()->after('employee_id');
            }
            if (!Schema::hasColumn('delivery_project_employee', 'member_name')) {
                $table->string('member_name')->nullable()->after('vendor_id');
            }
            if (!Schema::hasColumn('delivery_project_employee', 'member_position')) {
                $table->string('member_position')->nullable()->after('member_name');
            }
        });

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
            $table->index('vendor_id');
            $table->foreign('vendor_id')->references('customer_id')->on('customer')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropForeign(['vendor_id']);
            $table->dropIndex(['vendor_id']);
            $table->dropForeign(['employee_id']);
        });

        // Baris vendor tidak punya employee — tidak bisa dipertahankan saat
        // employee_id kembali NOT NULL.
        \DB::table('delivery_project_employee')->whereNull('employee_id')->delete();

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropColumn(['vendor_id', 'member_name', 'member_position']);
            $table->unsignedBigInteger('employee_id')->nullable(false)->change();
        });

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->foreign('employee_id')->references('employee_id')->on('employee')->onDelete('cascade');
        });
    }
};
