<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Tambah kolom notes pada pivot table
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('end_date');
        });

        // 2. Rename role "Co PM" → "Co Project Manager"
        DB::table('delivery_project_employee')
            ->where('role', 'Co PM')
            ->update(['role' => 'Co Project Manager']);

        // 3. Rename role "Head" → "Lead"
        DB::table('delivery_project_employee')
            ->where('role', 'Head')
            ->update(['role' => 'Lead']);
    }

    public function down(): void
    {
        // Revert role renames
        DB::table('delivery_project_employee')
            ->where('role', 'Co Project Manager')
            ->update(['role' => 'Co PM']);

        DB::table('delivery_project_employee')
            ->where('role', 'Lead')
            ->update(['role' => 'Head']);

        // Drop notes column
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
