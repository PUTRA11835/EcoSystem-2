<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel referensi Position — single source of truth untuk opsi dropdown
 * Position di form employee basic data (master/profile).
 *
 * Catatan desain (sama seperti tabel `grades`): `employee_basic_data.position`
 * TETAP menyimpan nama position sebagai string (penghubung lewat `positions.name`),
 * bukan FK — agar minim disrupsi & import tetap berbasis nama. Data lama (~78 nilai
 * posisi konsultan yang sudah dipakai) TIDAK ikut di-seed di sini karena vocabulary-nya
 * beda dari daftar standar HR di bawah; nilai lama tetap tersimpan & tampil apa adanya
 * di record masing-masing (lihat custom-dropdown.js -> setCustomDropdownValue()).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $names = [
            'PRESIDENT DIRECTOR',
            'DIRECTOR FINANCE, HR & GENERAL',
            'HEAD OF FINANCE',
            'FINANCE STAFF',
            'FINANCE INTERNSHIP',
            'HEAD OF ACCOUNTING & CONTROL',
            'ACCOUNTING & TAX',
            'ACCOUNTING STAFF',
            'CONTROL',
            'ACCOUNTING & CONTROL INTERNSHIP',
            'HEAD OF HUMAN CAPITAL & GENERAL AFFAIR',
            'HUMAN CAPITAL ADMINISTRATION STAFF',
            'GENERAL AFFAIR ADMINISTRATION STAFF',
            'HUMAN CAPITAL & GENERAL AFFAIR INTERNSHIP',
            'HEAD OF LEGAL CORPORATION',
            'LEGAL STAFF',
            'LEGAL CORPORATION INTERNSHIP',
            'DRIVER',
            'INTERNAL DEVELOPER',
            'INTERNAL DEVELOPER INTERNSHIP',
            'DIRECTOR BUSINESS DEVELOPMENT',
            'VICE DIRECTOR BUSINESS DEVELOPMENT',
            'HEAD OF PRE-SALES & SOLUTION ARCHITECT',
            'PRESALES',
            'SOLUTION ARCHITECH',
            'CUSTOMER SUCCESS',
            'HEAD OF SALES & MARKETING',
            'SENIOR BUSINESS DEVELOPMENT',
            'BUSINESS DEVELOPMENT EXCECUTIVE',
            'SALES ADMIN',
            'SALES & MARKETING INTERNSHIP',
            'DIRECTOR PROJECT & OPERATIONAL',
            'VICE DIRECTOR PROJECT & OPERATIONAL',
            'HEAD OF DELIVERY SUPPORT & HELPDESK',
            'HELPDESK',
            'SENIOR HELPDESK',
            'HEAD OF DELIVERY PROJECT',
            'DELIVERY PROJECT ADMINISTRATION',
            'HEAD OF RPMO',
            'RPMO INTERNSHIP',
            'PROJECT MANAGEMENT OFFICER',
            'SAP CONSULTANT',
            'YONYOU CONSULTANT',
        ];

        $now  = now();
        $rows = [];
        foreach ($names as $i => $name) {
            $rows[] = [
                'name'       => $name,
                'sort_order' => $i + 1,
                'is_active'  => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }
        DB::table('positions')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};
