<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Penandatangan slip (HR & Finance) dipilih dari ROLE lalu USER (dropdown bisa dicari), bukan diketik.
 * Kolom nama/jabatan lama (`*_signer_name`, `*_signer_title`) tetap menjadi SNAPSHOT teks yang dicetak di slip — jadi slip lama
 * dan data yang sudah diketik tidak berubah. Kolom baru hanya mengingat pilihan role & karyawannya supaya form menampilkannya lagi.
 * Aditif dan boleh kosong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->unsignedBigInteger('hr_signer_role_id')->nullable();
            $table->unsignedBigInteger('hr_signer_employee_id')->nullable();
            $table->unsignedBigInteger('finance_signer_role_id')->nullable();
            $table->unsignedBigInteger('finance_signer_employee_id')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('payroll_settings', function (Blueprint $table) {
            $table->dropColumn(['hr_signer_role_id', 'hr_signer_employee_id', 'finance_signer_role_id', 'finance_signer_employee_id']);
        });
    }
};
