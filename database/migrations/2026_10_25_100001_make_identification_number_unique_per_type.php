<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hasil UAT: NPWP tak bisa disimpan karena nomornya sama dengan KTP (NPWP 16 digit = NIK). Indeks UNIK lama ada pada
 * identification_number saja (global); diganti UNIK pada (identification_type, identification_number), jadi nomor tetap tak boleh
 * ganda DALAM satu jenis, tetapi boleh sama antarjenis. Tidak ada baris yang diubah/dihapus.
 */
return new class extends Migration
{
    private const OLD = 'employee_identification_identification_number_unique';
    private const NEW = 'employee_identification_type_number_unique';

    private function hasIndex(string $name): bool
    {
        return DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'employee_identification')
            ->where('index_name', $name)
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasTable('employee_identification')) {
            return;
        }
        // Tambah indeks baru dulu (gagal bila ada ganda dalam satu jenis), baru lepas yang lama.
        if (! $this->hasIndex(self::NEW)) {
            Schema::table('employee_identification', fn ($t) => $t->unique(['identification_type', 'identification_number'], self::NEW));
        }
        if ($this->hasIndex(self::OLD)) {
            Schema::table('employee_identification', fn ($t) => $t->dropUnique(self::OLD));
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('employee_identification')) {
            return;
        }
        // Pengembalian hanya aman bila belum ada nomor yang sama lintas jenis.
        $dup = DB::table('employee_identification')->select('identification_number')->groupBy('identification_number')->havingRaw('COUNT(*) > 1')->exists();
        if (! $dup && ! $this->hasIndex(self::OLD)) {
            Schema::table('employee_identification', fn ($t) => $t->unique('identification_number', self::OLD));
        }
        if ($this->hasIndex(self::NEW) && ! $dup) {
            Schema::table('employee_identification', fn ($t) => $t->dropUnique(self::NEW));
        }
    }
};
