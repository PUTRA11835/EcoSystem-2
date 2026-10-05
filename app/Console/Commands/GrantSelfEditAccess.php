<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Lengkapi izin UBAH seksi My Profile untuk satu role (HC-D53).
 *
 * KENAPA ADA: layar Control Center › Menu Access punya baris GRUP ("Basic Data") dan baris ANAK ("View Basic Data",
 * "Update Basic Data"). Sistem menentukan View Only vs bisa mengubah lewat baris anak `my-profile.section.<seksi>.update`
 * — kotak C/E/D pada baris grup TIDAK dipakai dan tidak menurun ke anak (hanya centang V yang menurun). Akibatnya
 * role yang kotak grupnya dicentang tetap View Only. Perintah ini menambahkan baris anak "Update …" yang kurang.
 *
 * AMAN: hanya MENAMBAH izin untuk role yang disebut (tidak menyentuh role lain, tidak mencabut apa pun) dan idempoten.
 * Seksi `contract` dan `payment` (data kontrak/gaji) TIDAK ikut kecuali `--include-contract-payment`.
 * Jalankan `--dry-run` dulu. Cara manual di layar: buka grup seksi (panah ▸) lalu centang "Update …".
 */
class GrantSelfEditAccess extends Command
{
    protected $signature = 'hc:grant-self-edit
        {role : Nama role persis (mis. "Delivery Support User")}
        {--include-contract-payment : Ikutkan seksi Contract dan Basic Payment}
        {--dry-run : Tampilkan tanpa mengubah}';

    protected $description = 'Tambahkan izin Update seksi My Profile (anak "Update …") untuk satu role';

    private const SECTIONS = ['basic_data', 'address', 'identification', 'family', 'education', 'qualification', 'bank', 'attachment'];

    public function handle(): int
    {
        $role = DB::table('employee_role')->where('name', (string) $this->argument('role'))->first();
        if (!$role) {
            $this->error('Role tidak ditemukan: ' . $this->argument('role'));

            return self::FAILURE;
        }

        $sections = self::SECTIONS;
        if ($this->option('include-contract-payment')) {
            $sections = array_merge($sections, ['contract', 'payment']);
        }
        // hr_profile hanya diberikan bila sudah ada View-nya (data pribadi/foto/tanda tangan); ditangani perintah lain.
        $slugs = array_map(fn ($s) => "my-profile.section.$s.update", $sections);
        $menus = DB::table('menu')->whereIn('slug', $slugs)->pluck('id', 'slug');

        $missing = array_diff($slugs, $menus->keys()->all());
        if ($missing) {
            $this->warn('Slug tidak ada di tabel menu (dilewati): ' . implode(', ', $missing));
        }

        $existing = DB::table('role_menu')->where('role_id', $role->id)->whereIn('menu_id', $menus->values())->where('can_view', 1)->pluck('menu_id')->all();
        $toAdd = $menus->filter(fn ($id) => !in_array($id, $existing, true));

        $this->info("Role: {$role->name} (id {$role->id}) · pegawai: " . DB::table('employee_role_assignment')->where('role_id', $role->id)->distinct()->count('employee_id'));
        $this->line('Sudah punya : ' . ($existing ? implode(', ', array_map(fn ($id) => $menus->search($id), $existing)) : '-'));
        $this->line('Akan ditambah: ' . ($toAdd->isNotEmpty() ? implode(', ', $toAdd->keys()->all()) : '-'));

        if ($this->option('dry-run') || $toAdd->isEmpty()) {
            $this->warn($this->option('dry-run') ? '--dry-run: tidak ada yang diubah.' : 'Tidak ada yang perlu ditambah.');

            return self::SUCCESS;
        }

        $now = now();
        foreach ($toAdd as $menuId) {
            DB::table('role_menu')->updateOrInsert(
                ['role_id' => $role->id, 'menu_id' => $menuId],
                ['can_view' => 1, 'can_create' => 0, 'can_edit' => 0, 'can_delete' => 0, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // Cache izin pegawai role ini dibuang agar langsung berlaku (ShareMenuPermissions menyimpannya 60 menit).
        DB::table('employee_role_assignment')->where('role_id', $role->id)->pluck('employee_id')->unique()->each(function ($id) {
            Cache::forget("perm_slugs_{$id}");
            Cache::forget("perm_matrix_{$id}");
        });

        $this->info('Selesai. ' . $toAdd->count() . ' izin Update ditambahkan; pegawai role ini dapat mengubah seksi tersebut di My Profile.');

        return self::SUCCESS;
    }
}
