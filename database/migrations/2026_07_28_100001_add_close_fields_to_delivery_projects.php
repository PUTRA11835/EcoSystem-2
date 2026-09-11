<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Manual "Close Project": menandai project selesai secara eksplisit (override
 * auto-category yang diturunkan dari progress planning) sekaligus mengunci edit
 * (read-only) sampai di-reopen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->boolean('is_closed')->default(false)->after('category');
            $table->timestamp('closed_at')->nullable()->after('is_closed');
            $table->unsignedBigInteger('closed_by')->nullable()->after('closed_at');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_projects', function (Blueprint $table) {
            $table->dropColumn(['is_closed', 'closed_at', 'closed_by']);
        });
    }
};
