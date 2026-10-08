<?php

use App\Support\MenuRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Tab Letters (BPJS Letter) di hub BPJS (Fase 4).
 *   finance.bpjs.letters   page   C = buat surat penonaktifan · D = batalkan (void) surat
 * Surat disimpan lewat modul Letters yang sudah ada (jenis `bpjs_deactivation`), jadi muncul juga di Letter Register
 * dan kop suratnya diatur di Letter Templates → Settings.
 * ATURAN BAKU MenuRegistrar: slug baru hanya aktif untuk EC Administrator.
 */
return new class extends Migration
{
    public function up(): void
    {
        MenuRegistrar::register('finance.bpjs', ['finance.bpjs.letters' => 'Letters'], 3, 'page');
        MenuRegistrar::grantToAdminAndRoles(['finance.bpjs.letters'], [], ['create', 'delete']);
        $this->flushPermCache();
    }

    public function down(): void
    {
        MenuRegistrar::remove(['finance.bpjs.letters']);
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
