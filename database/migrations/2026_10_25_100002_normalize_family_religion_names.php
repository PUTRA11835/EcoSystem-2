<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil UAT: dropdown Religion di Family memakai nama berbeda dari Basic Data (Christianity/Catholicism/Hinduism/Buddhism).
 * Nama lama disamakan dengan Basic Data. Hanya nilai persis yang disebut yang diubah; nilai lain ('Other', kosong) tidak disentuh.
 */
return new class extends Migration
{
    private const MAP = [
        'Christianity' => 'Christian',
        'Catholicism' => 'Catholic',
        'Hinduism' => 'Hindu',
        'Buddhism' => 'Buddhist',
        'Confucian' => 'Konghucu',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('employee_family')) {
            return;
        }
        foreach (self::MAP as $old => $new) {
            DB::table('employee_family')->where('religion', $old)->update(['religion' => $new]);
        }
    }

    public function down(): void
    {
        // Tidak dikembalikan: nama lama tidak baku dan tidak dipakai lagi.
    }
};
