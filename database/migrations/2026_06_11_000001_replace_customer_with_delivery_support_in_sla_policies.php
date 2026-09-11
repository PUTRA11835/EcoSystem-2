<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropIndex('sla_policies_unique');
            $table->dropIndex('sla_policies_lookup_idx');
        });

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('customer_id');
            $table->unsignedBigInteger('delivery_support_id')->nullable()->after('id');

            $table->unique(['delivery_support_id', 'priority', 'scale'], 'sla_policies_unique');
            $table->index(['delivery_support_id', 'priority', 'scale', 'is_active'], 'sla_policies_lookup_idx');

            $table->foreign('delivery_support_id')
                  ->references('id')->on('delivery_support')
                  ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropForeign(['delivery_support_id']);
            $table->dropIndex('sla_policies_unique');
            $table->dropIndex('sla_policies_lookup_idx');
        });

        Schema::table('sla_policies', function (Blueprint $table) {
            $table->dropColumn('delivery_support_id');
            $table->unsignedBigInteger('customer_id')->nullable()->after('id');

            $table->unique(['customer_id', 'priority', 'scale'], 'sla_policies_unique');
            $table->index(['customer_id', 'priority', 'scale', 'is_active'], 'sla_policies_lookup_idx');

            $table->foreign('customer_id')
                  ->references('customer_id')->on('customer')
                  ->nullOnDelete();
        });
    }
};
