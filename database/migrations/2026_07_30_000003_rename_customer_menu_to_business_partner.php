<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Master "Customer" berganti nama tampilan menjadi "Business Partner".
 * Slug menu (`master.customer`, `master.customer.create`, …) SENGAJA tidak diubah
 * supaya seluruh grant di `role_menu` dan middleware `menu:` yang sudah ada tetap
 * berlaku — yang berubah hanya label yang dilihat user.
 */
return new class extends Migration
{
    private const RENAMES = [
        'master.customer'        => ['Business Partner', 'Customer'],
        'master.customer.create' => ['Create Business Partner', 'Create Customer'],
    ];

    public function up(): void
    {
        foreach (self::RENAMES as $slug => [$new, $old]) {
            DB::table('menu')->where('slug', $slug)->update(['name' => $new, 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        foreach (self::RENAMES as $slug => [$new, $old]) {
            DB::table('menu')->where('slug', $slug)->update(['name' => $old, 'updated_at' => now()]);
        }
    }
};
