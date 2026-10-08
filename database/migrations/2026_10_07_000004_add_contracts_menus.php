<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Menu Access menu Contract (HC-D66), di bawah HR & General:
 *
 *   Contract                     general.contracts            (group; hanya induk tab di Menu Access)
 *   ├─ Contracts                 general.contracts.list       page   C buat · E ubah/ganti status · D hapus
 *   ├─ Templates                 general.contracts.templates  page   C buat · E ubah · D hapus
 *   └─ View Salary               general.contracts.salary     function (sensitif: nilai gaji di kontrak)
 *
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator; pembagian ke role HR dilakukan di
 * Management → Roles → Menu Access. Peluncuran ini tidak mengubah perilaku siapa pun yang sudah live.
 */
return new class extends Migration
{
    private const TABS = [
        'general.contracts.list'      => 'Contracts',
        'general.contracts.templates' => 'Templates',
    ];

    public function up(): void
    {
        if (!MenuRegistrar::register('general', ['general.contracts' => 'Contract'], 78, 'group')) {
            return;
        }

        $seq = 1;
        foreach (self::TABS as $slug => $name) {
            MenuRegistrar::register('general.contracts', [$slug => $name], $seq++, 'page');
        }
        MenuRegistrar::register('general.contracts', ['general.contracts.salary' => 'View Salary'], 10, 'function');

        // Dua tab menegakkan kotak C/E/D di server (menu.can) — EC Administrator mulai dengan ketiganya.
        MenuRegistrar::grantToAdminAndRoles(array_keys(self::TABS), [], ['create', 'edit', 'delete']);

        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['general.contracts.salary', ...array_keys(self::TABS), 'general.contracts']);
        $this->flushPermCache();
    }

    private function flushPermCache(): void
    {
        DB::table('employee_role_assignment')->pluck('employee_id')->unique()->each(function ($employeeId) {
            Cache::forget("perm_slugs_{$employeeId}");
            Cache::forget("perm_matrix_{$employeeId}");
        });
    }
};
