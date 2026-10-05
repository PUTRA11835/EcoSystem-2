<?php

namespace App\Console\Commands;

use App\Support\MenuRegistrar;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Beri bagian "Photo, signature & HR information" (slug `my-profile.section.hr_profile.*`) kepada role yang SUDAH punya
 * seksi Basic Data di My Profile (HC-D52).
 *
 * KENAPA PERINTAH, BUKAN MIGRASI: aturan baku MenuRegistrar — izin baru hanya EC Administrator dan tidak menyebar
 * diam-diam saat deploy. Penyebaran ke role lain adalah KEPUTUSAN pemilik, jadi dijalankan sadar (`--dry-run` dulu).
 * Idempoten: dijalankan ulang menghasilkan keadaan yang sama (role = EC Administrator + role pemegang Basic Data).
 *
 * Hanya menambah izin melihat/mengisi data pribadi sendiri, foto, dan tanda tangan. TIDAK memberi izin ubah
 * Identification/Bank/Address/Basic Data, dan tidak memberi akses ke data orang lain.
 */
class GrantHrInfoSelfAccess extends Command
{
    protected $signature = 'hc:grant-hr-info-self {--dry-run : Tampilkan role yang akan menerima tanpa mengubah apa pun}';

    protected $description = 'Beri bagian foto/tanda tangan/informasi HR di My Profile kepada role yang sudah punya Basic Data di My Profile';

    private const SLUGS = ['my-profile.section.hr_profile.view', 'my-profile.section.hr_profile.update'];

    public function handle(): int
    {
        if (DB::table('menu')->whereIn('slug', self::SLUGS)->count() !== count(self::SLUGS)) {
            $this->error('Slug my-profile.section.hr_profile.* belum ada. Jalankan php artisan migrate dulu.');

            return self::FAILURE;
        }

        $roles = DB::table('role_menu as rm')
            ->join('menu as m', 'm.id', '=', 'rm.menu_id')
            ->join('employee_role as r', 'r.id', '=', 'rm.role_id')
            ->where('m.slug', 'my-profile.section.basic_data.view')
            ->where('rm.can_view', 1)
            ->orderBy('r.id')
            ->get(['r.id', 'r.name']);

        $employees = DB::table('employee_role_assignment')->whereIn('role_id', $roles->pluck('id'))->distinct()->count('employee_id');

        $this->info('Role yang akan menerima (sudah punya Basic Data di My Profile):');
        foreach ($roles as $r) {
            $this->line("  - {$r->id}: {$r->name}");
        }
        $this->info("Pegawai terdampak: {$employees}");

        if ($this->option('dry-run')) {
            $this->warn('--dry-run: tidak ada yang diubah.');

            return self::SUCCESS;
        }

        MenuRegistrar::grantToAdminAndRoles(self::SLUGS, $roles->pluck('name')->all());
        $this->info('Selesai. Bagian "Photo, signature & HR information" kini tampil di tab Basic Data My Profile untuk role di atas.');

        return self::SUCCESS;
    }
}
