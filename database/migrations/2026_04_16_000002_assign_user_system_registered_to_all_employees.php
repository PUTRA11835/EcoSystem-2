<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Ambil ID dari role 'User System Registered' yang baru saja diinsert
        $roleId = DB::table('employee_role')
            ->where('name', 'User System Registered')
            ->value('id');

        if (!$roleId) {
            // Role belum ada — lewati (seharusnya tidak terjadi jika migrasi sebelumnya sudah jalan)
            return;
        }

        // Assign role ini ke semua employee yang belum memilikinya
        $allEmployeeIds = DB::table('employee')->pluck('employee_id');

        $existing = DB::table('employee_role_assignment')
            ->where('role_id', $roleId)
            ->pluck('employee_id')
            ->toArray();

        $now = now();

        $toInsert = $allEmployeeIds
            ->reject(fn($id) => in_array($id, $existing))
            ->map(fn($id) => [
                'employee_id' => $id,
                'role_id'     => $roleId,
                'created_at'  => $now,
                'updated_at'  => $now,
            ])
            ->values()
            ->toArray();

        if (!empty($toInsert)) {
            DB::table('employee_role_assignment')->insertOrIgnore($toInsert);
        }
    }

    public function down(): void
    {
        $roleId = DB::table('employee_role')
            ->where('name', 'User System Registered')
            ->value('id');

        if ($roleId) {
            DB::table('employee_role_assignment')->where('role_id', $roleId)->delete();
        }
    }
};
