<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            // IO Number — unik per delivery support (satu IO Number hanya boleh
            // dipakai oleh satu support). Nullable karena boleh belum diisi.
            $table->string('io_number')->nullable()->after('sales_id');
            $table->decimal('revenue', 20, 2)->nullable()->after('io_number');
            $table->decimal('plan_cost', 20, 2)->nullable()->after('revenue');
            $table->decimal('gross_profit', 20, 2)->nullable()->after('plan_cost');
            $table->decimal('gross_profit_percentage', 8, 2)->nullable()->after('gross_profit');

            $table->unique('io_number');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_support', function (Blueprint $table) {
            $table->dropUnique(['io_number']);
            $table->dropColumn([
                'io_number',
                'revenue',
                'plan_cost',
                'gross_profit',
                'gross_profit_percentage',
            ]);
        });
    }
};
