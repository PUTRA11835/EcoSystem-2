<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->unsignedBigInteger('co_pm_id')->nullable()->after('support_manager_id');
            $table->unsignedBigInteger('support_admin_id')->nullable()->after('co_pm_id');
            $table->unsignedBigInteger('sales_id')->nullable()->after('support_admin_id');

            $table->foreign('co_pm_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('support_admin_id')->references('employee_id')->on('employee')->nullOnDelete();
            $table->foreign('sales_id')->references('employee_id')->on('employee')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->dropForeign(['co_pm_id']);
            $table->dropForeign(['support_admin_id']);
            $table->dropForeign(['sales_id']);
            $table->dropColumn(['co_pm_id', 'support_admin_id', 'sales_id']);
        });
    }
};
