<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->string('role')->nullable()->after('module');           // 'Member' | 'Head'
            $table->string('employee_type')->default('Internal')->after('role'); // 'Internal' | 'External' | 'Vendor'
            $table->string('vendor_name')->nullable()->after('employee_type');
        });

        // Migrate existing assignment data to role, then drop the old column
        \DB::statement("UPDATE delivery_project_employee SET role = assignment WHERE assignment IS NOT NULL");

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropColumn('assignment');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->string('assignment')->nullable()->after('module');
        });

        \DB::statement("UPDATE delivery_project_employee SET assignment = role WHERE role IS NOT NULL");

        Schema::table('delivery_project_employee', function (Blueprint $table) {
            $table->dropColumn(['role', 'employee_type', 'vendor_name']);
        });
    }
};
