<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

/**
 * Settings-side 2FA management (enable/confirm/disable/regenerate). Kept
 * separate from AuthController, which stays focused on the login-time
 * challenge — see app/Services/TwoFactorAuthService.php for the primitives
 * both controllers share.
 */
class TwoFactorController extends Controller
{
    private function currentAuthUser(): ?object
    {
        $user = session('user');

        if (!$user || ($user['type'] ?? null) !== 'employee' || empty($user['id'])) {
            return null;
        }

        return DB::table('auth_users')->where('employee_id', $user['id'])->first();
    }

    /** Human-readable identifier for the audit trail - never the secret/password itself. */
    private function auditLabel(object $authUser): string
    {
        return session('user.name') ?? $authUser->email ?? $authUser->username ?? "Auth User #{$authUser->id}";
    }

    /**
     * Generates a new (unconfirmed) secret and returns the QR code + manual
     * entry key. Confirmed 2FA is untouched until /confirm succeeds — this
     * can be called again to restart enrollment (e.g. lost the QR) without
     * side effects on an already-active setup, since confirmed_at only gets
     * cleared by /disable.
     */
    public function enable(Request $request)
    {
        $authUser = $this->currentAuthUser();

        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (TwoFactorAuthService::isEnabled($authUser)) {
            return response()->json(['success' => false, 'message' => 'Two-factor authentication is already enabled.'], 422);
        }

        $secret = TwoFactorAuthService::generateSecretKey();

        DB::table('auth_users')->where('id', $authUser->id)->update([
            'two_factor_secret'         => TwoFactorAuthService::encryptSecret($secret),
            'two_factor_confirmed_at'   => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_used_at'   => null,
        ]);

        $accountLabel = $authUser->email ?: $authUser->username;

        AuditLog::recordAction(
            module: 'Security',
            auditableType: 'AuthUser',
            auditableId: $authUser->id,
            event: 'updated',
            recordLabel: $this->auditLabel($authUser),
            description: 'started two-factor authentication enrollment',
            old: null,
            new: null,
        );

        return response()->json([
            'success' => true,
            'data'    => [
                'secret'      => $secret,
                'qr_svg'      => TwoFactorAuthService::getQrCodeSvg($accountLabel, $secret),
                'otpauth_uri' => TwoFactorAuthService::getOtpAuthUri($accountLabel, $secret),
            ],
        ]);
    }

    /**
     * Confirms enrollment with a code from the authenticator app. On
     * success, generates recovery codes and returns them in plaintext once
     * — they are never retrievable again after this response.
     */
    public function confirm(Request $request)
    {
        $authUser = $this->currentAuthUser();

        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), ['code' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        if (empty($authUser->two_factor_secret)) {
            return response()->json(['success' => false, 'message' => 'Start enrollment first.'], 422);
        }

        $secret = TwoFactorAuthService::decryptSecret($authUser->two_factor_secret);
        if (!$secret) {
            return response()->json(['success' => false, 'message' => 'Enrollment data is invalid — please start again.'], 422);
        }

        $result = TwoFactorAuthService::verifyCode($secret, trim($request->input('code')), $authUser->two_factor_last_used_at);

        if (!$result['valid']) {
            return response()->json(['success' => false, 'message' => 'Invalid code. Please try again.'], 422);
        }

        $recoveryCodes = TwoFactorAuthService::generateRecoveryCodes(
            (int) config('security_center.two_factor.recovery_codes_count', 8)
        );

        DB::table('auth_users')->where('id', $authUser->id)->update([
            'two_factor_confirmed_at'   => now(),
            'two_factor_last_used_at'   => $result['timestamp'],
            'two_factor_recovery_codes' => json_encode(TwoFactorAuthService::hashRecoveryCodes($recoveryCodes)),
        ]);

        AuditLog::recordAction(
            module: 'Security',
            auditableType: 'AuthUser',
            auditableId: $authUser->id,
            event: 'updated',
            recordLabel: $this->auditLabel($authUser),
            description: 'confirmed and enabled two-factor authentication',
            old: ['two_factor_enabled' => false],
            new: ['two_factor_enabled' => true],
        );

        return response()->json([
            'success' => true,
            'message' => 'Two-factor authentication enabled.',
            'data'    => ['recovery_codes' => $recoveryCodes],
        ]);
    }

    /** Requires the account's current password — clears all 2FA columns. */
    public function disable(Request $request)
    {
        $authUser = $this->currentAuthUser();

        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validator = Validator::make($request->all(), ['password' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        if (!Hash::check($request->input('password'), $authUser->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 422);
        }

        DB::table('auth_users')->where('id', $authUser->id)->update([
            'two_factor_secret'         => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at'   => null,
            'two_factor_last_used_at'   => null,
        ]);

        AuditLog::recordAction(
            module: 'Security',
            auditableType: 'AuthUser',
            auditableId: $authUser->id,
            event: 'updated',
            recordLabel: $this->auditLabel($authUser),
            description: 'disabled two-factor authentication',
            old: ['two_factor_enabled' => true],
            new: ['two_factor_enabled' => false],
        );

        return response()->json(['success' => true, 'message' => 'Two-factor authentication disabled.']);
    }

    /** Requires the account's current password — replaces the recovery code set. */
    public function regenerateRecoveryCodes(Request $request)
    {
        $authUser = $this->currentAuthUser();

        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        if (!TwoFactorAuthService::isEnabled($authUser)) {
            return response()->json(['success' => false, 'message' => 'Two-factor authentication is not enabled.'], 422);
        }

        $validator = Validator::make($request->all(), ['password' => 'required|string']);
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()->first()], 422);
        }

        if (!Hash::check($request->input('password'), $authUser->password)) {
            return response()->json(['success' => false, 'message' => 'Incorrect password.'], 422);
        }

        $recoveryCodes = TwoFactorAuthService::generateRecoveryCodes(
            (int) config('security_center.two_factor.recovery_codes_count', 8)
        );

        DB::table('auth_users')->where('id', $authUser->id)->update([
            'two_factor_recovery_codes' => json_encode(TwoFactorAuthService::hashRecoveryCodes($recoveryCodes)),
        ]);

        AuditLog::recordAction(
            module: 'Security',
            auditableType: 'AuthUser',
            auditableId: $authUser->id,
            event: 'updated',
            recordLabel: $this->auditLabel($authUser),
            description: 'regenerated two-factor authentication recovery codes (previous codes invalidated)',
            old: null,
            new: null,
        );

        return response()->json([
            'success' => true,
            'data'    => ['recovery_codes' => $recoveryCodes],
        ]);
    }
}
