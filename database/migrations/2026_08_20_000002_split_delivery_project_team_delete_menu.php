<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Team Members: pisahkan "hapus" dari "tambah".
 *
 * Sebelumnya satu slug `delivery-project.team.manage` ("Create / Delete")
 * membundel tambah + hapus, dan tombol hapus memang tidak pernah ada di UI.
 * Sekarang ada kebutuhan menghapus anggota tim, dan role yang boleh menghapus
 * TIDAK sama dengan role yang boleh menambah — jadi aksinya dipecah:
 *
 *   delivery-project.team.manage  → Create  (tambah anggota)
 *   delivery-project.team.delete  → Delete  (hapus anggota)  ← BARU
 *
 * Sesuai App\Support\MenuRegistrar, slug baru lahir aktif HANYA untuk EC
 * Administrator. Grant `manage` yang sudah ada TIDAK diwariskan ke `delete`:
 * kemampuan menghapus harus diputuskan eksplisit lewat Control Center.
 */
return new class extends Migration
{
    private const NEW_SLUG = 'delivery-project.team.delete';
    private const MANAGE_SLUG = 'delivery-project.team.manage';

    public function up(): void
    {
        $manage = DB::table('menu')->where('slug', self::MANAGE_SLUG)->first();
        if (!$manage) {
            return;
        }

        // Label lama "Create / Delete" jadi menyesatkan setelah dipecah.
        DB::table('menu')
            ->where('id', $manage->id)
            ->update(['name' => 'Team Members — Create', 'updated_at' => now()]);

        // Sisipkan tepat setelah "Create" supaya urutannya di modal Menu Access
        // tetap View → Edit → Create → Delete.
        $seq = (int) $manage->order_seq + 1;

        DB::table('menu')
            ->where('parent_id', $manage->parent_id)
            ->where('order_seq', '>=', $seq)
            ->increment('order_seq');

        MenuRegistrar::register('delivery.project', [
            self::NEW_SLUG => 'Team Members — Delete',
        ], $seq);
    }

    public function down(): void
    {
        $menu = DB::table('menu')->where('slug', self::NEW_SLUG)->first();

        MenuRegistrar::remove([self::NEW_SLUG]);

        if ($menu) {
            DB::table('menu')
                ->where('parent_id', $menu->parent_id)
                ->where('order_seq', '>', $menu->order_seq)
                ->decrement('order_seq');
        }

        DB::table('menu')
            ->where('slug', self::MANAGE_SLUG)
            ->update(['name' => 'Team Members — Create / Delete', 'updated_at' => now()]);
    }
};
