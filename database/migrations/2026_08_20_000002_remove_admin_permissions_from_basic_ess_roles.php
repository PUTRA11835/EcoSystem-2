<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $basicRoleIds = DB::table('employee_role')
            ->whereIn('name', ['EC User', 'User System Registered'])
            ->orWhereIn('id', [3, 55])
            ->pluck('id');

        $adminMenuIds = DB::table('menu')
            ->whereIn('slug', ['general', 'hr_general.leave_permit', 'tickets.inbox', 'ticket.all-tickets'])
            ->pluck('id');

        if ($basicRoleIds->isNotEmpty() && $adminMenuIds->isNotEmpty()) {
            DB::table('role_menu')
                ->whereIn('role_id', $basicRoleIds)
                ->whereIn('menu_id', $adminMenuIds)
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No-op
    }
};
