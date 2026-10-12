<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Brute-force lockout: temporarily locks an auth_users row after too many
 * failed login attempts (see LoginSecurityService). Keyed by auth_users.id
 * rather than employee_id/customer_id since this table serves both.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->timestamp('locked_until')->nullable()->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn('locked_until');
        });
    }
};
