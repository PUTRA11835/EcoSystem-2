<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Short-lived "2FA re-verification grace period" for the web login: after a
 * successful TOTP/recovery-code verification, the browser gets a device-bound
 * token (hashed here, matching the existing remember_token pattern) that lets
 * a subsequent login on the same device skip the OTP prompt — password is
 * still checked every time — until it expires (tied to config('session.lifetime')
 * at issue time, not extended by each skip). See TwoFactorAuthService.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->string('two_factor_trusted_token', 64)->nullable()->after('two_factor_last_used_at');
            $table->timestamp('two_factor_trusted_expires_at')->nullable()->after('two_factor_trusted_token');
        });
    }

    public function down(): void
    {
        Schema::table('auth_users', function (Blueprint $table) {
            $table->dropColumn(['two_factor_trusted_token', 'two_factor_trusted_expires_at']);
        });
    }
};
