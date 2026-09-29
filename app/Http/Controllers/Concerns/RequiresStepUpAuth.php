<?php

namespace App\Http\Controllers\Concerns;

use App\Models\SecurityEvent;
use App\Services\LoginSecurityService;
use App\Services\TwoFactorAuthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Gate for the handful of admin actions dangerous enough to need a fresh
 * TOTP code beyond the session's original login-time 2FA check (grant EC
 * Administrator role, unblock IP, unlock account). Every call requires a
 * fresh code - no time-limited "sudo mode" window - per confirmed product
 * decision.
 *
 * Returns 428 (Precondition Required) with `requires_step_up: true` when no
 * code was sent yet, so a caller that doesn't know in advance whether an
 * action needs step-up (e.g. changeRole() only needs it when the sync
 * actually grants admin) can try first and prompt only when told to.
 */
trait RequiresStepUpAuth
{
    protected function verifyStepUpOrFail(Request $request): ?JsonResponse
    {
        $employeeId = session('user.id');
        $authUser   = $employeeId ? DB::table('auth_users')->where('employee_id', $employeeId)->first() : null;

        if (!$authUser) {
            return response()->json(['success' => false, 'message' => 'Session invalid.'], 401);
        }

        if (TwoFactorAuthService::isChallengeLocked($authUser->id)) {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed verification attempts. Try again later.',
            ], 403);
        }

        $code = trim((string) $request->input('two_factor_code'));

        if ($code === '') {
            return response()->json([
                'success'          => false,
                'requires_step_up' => true,
                'message'          => 'Verification required to complete this action.',
            ], 428);
        }

        $result = TwoFactorAuthService::verifyForAuthUser($authUser->id, $code);

        if (!$result['verified']) {
            $attempts = TwoFactorAuthService::recordFailedChallenge($authUser->id);

            if (TwoFactorAuthService::isChallengeLocked($authUser->id)) {
                $lockedUntil = now()->addMinutes((int) config('security_center.two_factor.lockout_minutes', 30));
                DB::table('auth_users')->where('id', $authUser->id)->update(['locked_until' => $lockedUntil]);

                $event = SecurityEvent::record([
                    'event_type'         => 'step_up_verification_failed',
                    'severity'           => 'critical',
                    'module'             => 'Auth',
                    'status'             => 'open',
                    'title'              => 'Repeated step-up 2FA failures - account locked',
                    'description'        => (session('user.name') ?? "Employee #{$employeeId}") . " had {$attempts} failed step-up verification attempts on \"{$request->path()}\" and was auto-locked until {$lockedUntil->format('d M Y H:i')} - could be a hijacked session without the physical 2FA device, or a legitimate admin repeatedly mistyping.",
                    'target_employee_id' => $employeeId,
                    'ip_address'         => $request->ip(),
                    'payload'            => ['attempts' => $attempts, 'action' => $request->path()],
                ]);

                if ($event) {
                    app(LoginSecurityService::class)->notifyAdmins($event, 'Repeated step-up 2FA failures - account locked');
                }
            }

            return response()->json(['success' => false, 'message' => 'Invalid code.'], 401);
        }

        TwoFactorAuthService::clearFailedChallenge($authUser->id);

        return null;
    }
}
