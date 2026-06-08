<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Activity-level status: 'monitoring' is no longer a valid value.
     * Migrate existing rows in delivery_project_activities + delivery_project_planning
     * to 'in_progress' (closest semantic equivalent).
     */
    public function up(): void
    {
        DB::table('delivery_project_activities')
            ->where('status', 'monitoring')
            ->update(['status' => 'in_progress']);

        DB::table('delivery_project_planning')
            ->where('status', 'monitoring')
            ->update(['status' => 'in_progress']);
    }

    public function down(): void
    {
        // No-op: we cannot reliably identify which rows were originally 'monitoring'.
    }
};
