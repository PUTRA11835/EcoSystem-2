<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * RESTRUCTURE AUTH: CENTRALIZE AUTH TO auth_users TABLE
 * ============================================================================
 *
 * Changes:
 * 1. Remove user_type from auth_users (type is determined by employee_id/customer_id FK)
 * 2. Remove password from employee table (auth now via auth_users.password)
 * 3. Remove password from customer table (auth now via auth_users.password)
 *
 * @date 2026-02-19
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Remove user_type from auth_users & make phone nullable
        if (Schema::hasColumn('auth_users', 'user_type')) {
            Schema::table('auth_users', function (Blueprint $table) {
                $table->dropColumn('user_type');
            });
        }

        // Make phone nullable & drop unique constraint
        // (phone is optional; email/username/ECI is primary login identifier)
        if (Schema::hasColumn('auth_users', 'phone')) {
            Schema::table('auth_users', function (Blueprint $table) {
                // Drop unique index jika ada
                try { $table->dropUnique('auth_users_phone_unique'); } catch (\Exception $e) {}
                $table->string('phone')->nullable()->change();
            });
        }

        // 2. Remove password from employee
        if (Schema::hasColumn('employee', 'password')) {
            Schema::table('employee', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }

        // 3. Remove password from customer
        if (Schema::hasColumn('customer', 'password')) {
            Schema::table('customer', function (Blueprint $table) {
                $table->dropColumn('password');
            });
        }
    }

    public function down(): void
    {
        // Restore user_type to auth_users
        if (!Schema::hasColumn('auth_users', 'user_type')) {
            Schema::table('auth_users', function (Blueprint $table) {
                $table->enum('user_type', ['employee', 'customer'])->after('id');
            });
        }

        // Restore password to employee
        if (!Schema::hasColumn('employee', 'password')) {
            Schema::table('employee', function (Blueprint $table) {
                $table->string('password')->nullable()->after('eci');
            });
        }

        // Restore password to customer
        if (!Schema::hasColumn('customer', 'password')) {
            Schema::table('customer', function (Blueprint $table) {
                $table->string('password')->nullable()->after('email');
            });
        }
    }
};
