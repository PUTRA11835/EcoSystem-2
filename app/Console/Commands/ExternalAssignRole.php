<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Memasang role "External Consultant" ke konsultan eksternal yang belum memilikinya (konsultan lama dibuat sebelum role itu menjadi
 * bawaan Add Consultant). TIDAK mencabut role apa pun. Bawaan hanya mencetak daftar (dry-run); `--apply` menulis.
 *
 *   php artisan external:assign-role            # daftar saja
 *   php artisan external:assign-role --apply    # pasang
 */
class ExternalAssignRole extends Command
{
    protected $signature = 'external:assign-role {--apply : Write the assignments (default is a dry run)}';

    protected $description = 'Give the External Consultant role to active external consultants who lack it (dry run unless --apply).';

    public function handle(): int
    {
        $roleId = DB::table('employee_role')->where('name', 'External Consultant')->value('id');
        if (!$roleId) {
            $this->error('The External Consultant role does not exist. Run the migrations first.');

            return self::FAILURE;
        }

        $rows = DB::table('employee as e')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->where('e.is_active', 1)->where('b.employee_type', 'External')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('employee_role_assignment as a')->whereColumn('a.employee_id', 'e.employee_id')->where('a.role_id', $roleId))
            ->orderBy('e.employee_id')->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name']);

        $this->info($rows->count() . ' active external consultant(s) without the External Consultant role.');
        foreach ($rows as $r) {
            $this->line(sprintf('  %-6d %-12s %s', $r->employee_id, $r->eci, trim($r->first_name . ' ' . $r->last_name)));
        }

        if (!$this->option('apply')) {
            $this->warn('Dry run — nothing written. Add --apply to assign the role.');

            return self::SUCCESS;
        }

        foreach ($rows as $r) {
            DB::table('employee_role_assignment')->insertOrIgnore(['employee_id' => $r->employee_id, 'role_id' => $roleId, 'created_at' => now(), 'updated_at' => now()]);
            Cache::forget("perm_slugs_{$r->employee_id}");
            Cache::forget("perm_matrix_{$r->employee_id}");
        }
        $this->info('Assigned to ' . $rows->count() . ' consultant(s).');

        return self::SUCCESS;
    }
}
