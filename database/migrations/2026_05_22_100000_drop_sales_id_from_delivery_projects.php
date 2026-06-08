<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropForeign(['sales_id']);
            $table->dropColumn('sales_id');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->unsignedBigInteger('sales_id')->nullable()->after('project_admin_id');
            $table->foreign('sales_id')->references('employee_id')->on('employee')->nullOnDelete();
        });
    }
};
