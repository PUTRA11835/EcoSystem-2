<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->decimal('revenue', 20, 2)->nullable()->after('sales_id');
            $table->decimal('plan_cost', 20, 2)->nullable()->after('revenue');
            $table->decimal('gross_profit', 20, 2)->nullable()->after('plan_cost');
            $table->decimal('gross_profit_percentage', 8, 2)->nullable()->after('gross_profit');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropColumn(['revenue', 'plan_cost', 'gross_profit', 'gross_profit_percentage']);
        });
    }
};
