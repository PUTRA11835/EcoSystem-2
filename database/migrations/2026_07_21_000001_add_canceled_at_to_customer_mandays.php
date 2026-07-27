<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            if (!Schema::hasColumn('customer_mandays', 'canceled_at')) {
                $table->timestamp('canceled_at')->nullable()->after('canceled_by_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customer_mandays', function (Blueprint $table) {
            if (Schema::hasColumn('customer_mandays', 'canceled_at')) {
                $table->dropColumn('canceled_at');
            }
        });
    }
};
