<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Semua function-level permission sekarang dikelola di MenuSeeder
 * (sudah termasuk parent_id, urutan, dan permission matrix).
 * Seeder ini dipertahankan agar pemanggilan lama tidak error.
 */
class FunctionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(MenuSeeder::class);
    }
}
