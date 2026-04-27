<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('period_late_exception_requests', function (Blueprint $table) {
            // Deadline set by RPMO when approving — required before access is granted.
            $table->dateTime('expires_at')->nullable()->after('rpmo_notes');
        });
    }

    public function down(): void
    {
        Schema::table('period_late_exception_requests', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
