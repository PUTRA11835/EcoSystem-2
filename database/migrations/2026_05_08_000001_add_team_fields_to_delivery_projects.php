<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('project_owner_id')->nullable()->after('delivery_manager_id');
            $table->unsignedBigInteger('co_pm_id')->nullable()->after('project_owner_id');
            $table->unsignedBigInteger('project_admin_id')->nullable()->after('co_pm_id');
            $table->unsignedBigInteger('sales_id')->nullable()->after('project_admin_id');

            $table->foreign('project_owner_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('co_pm_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('project_admin_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('sales_id')->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropForeign(['project_owner_id']);
            $table->dropForeign(['co_pm_id']);
            $table->dropForeign(['project_admin_id']);
            $table->dropForeign(['sales_id']);
            $table->dropColumn(['project_owner_id', 'co_pm_id', 'project_admin_id', 'sales_id']);
        });
    }
};
