<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Legal Basis menjadi hub dua tab (permintaan pemilik 20 Okt 2026): Register (`finance.legal-basis`) dan Settings
 * (`finance.legal-basis.settings`) — saklar tampil / Draft mode / Enforce dipindah ke tab Settings dengan izin sendiri yang diatur Admin
 * di Menu Access. Slug baru hanya aktif untuk EC Administrator (aturan baku MenuRegistrar); role yang SUDAH memegang
 * `finance.legal-basis` menerima salinan kotaknya supaya tidak ada yang kehilangan akses. Hanya menambah baris; down() menghapusnya.
 */
return new class extends Migration
{
    private const SLUG = 'finance.legal-basis.settings';

    public function up(): void
    {
        if (!MenuRegistrar::register('finance.legal-basis', [self::SLUG => 'Settings'], 1, 'page')) {
            return;
        }

        $from = DB::table('menu')->where('slug', 'finance.legal-basis')->value('id');
        $to = DB::table('menu')->where('slug', self::SLUG)->value('id');
        $roles = [];
        foreach (DB::table('role_menu')->where('menu_id', $from)->get() as $g) {
            DB::table('role_menu')->updateOrInsert(['role_id' => $g->role_id, 'menu_id' => $to], [
                'can_view' => $g->can_view, 'can_create' => 0, 'can_edit' => $g->can_edit, 'can_delete' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            $roles[] = $g->role_id;
        }
        DB::table('employee_role_assignment')->whereIn('role_id', $roles)->pluck('employee_id')->unique()->each(function ($id) {
            Cache::forget("perm_slugs_{$id}");
            Cache::forget("perm_matrix_{$id}");
        });
    }

    public function down(): void
    {
        MenuRegistrar::remove([self::SLUG]);
    }
};
