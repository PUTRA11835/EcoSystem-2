<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel referensi Department — single source of truth untuk opsi dropdown
 * Department di form employee basic data (profile detail).
 *
 * Catatan desain (sama seperti tabel `grades`/`positions`): `employee_basic_data.department`
 * TETAP menyimpan nama department sebagai string (penghubung lewat `departments.name`),
 * bukan FK. Nilai lama ("System Operations", hanya 1 record) TIDAK ikut di-seed karena
 * beda vocabulary dari daftar standar HR di bawah; tetap tersimpan & tampil apa adanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        $names = [
            'FINANCE',
            'ACCOUNTING & CONTROL',
            'HUMAN CAPITAL & GENERAL AFFAIR',
            'LEGAL CORPORATION',
            'PRE-SALES & SOLUTION ARCHITECT',
            'CUSTOMER SUCCESS',
            'SALES & MARKETING',
            'DELIVERY SUPPORT & HELPDESK',
            'DELIVERY PROJECT',
            'RESOURCE, PROJECT MANAGEMENT & OPERATIONS',
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
        DB::table('departments')->insert($rows);
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
