<?php

namespace App\Services;

use App\Enums\RoleId;
use App\Models\LoginActivity;
use App\Models\Notification;
use App\Models\SecurityEvent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Brute-force lockout for the login flow. Failed-attempt counting reads
 * from the existing login_activity table rather than a separate counter,
 * so callers must write the failed login_activity row *before* calling
 * recordFailedAttempt(). Shared by AuthController and Lite\LiteAuthController
 * so both entry points enforce identical lockout behavior.
 */
class LoginSecurityService
{
    public function isIpBlocked(string $ip): bool
    {
        return DB::table('blocked_ips')
            ->where('ip_address', $ip)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->exists();
    }

    public function isAccountLocked(object $authUser): bool
    {
        return !empty($authUser->locked_until) && Carbon::parse($authUser->locked_until)->isFuture();
    }

    /**
     * Count recent failures and, on threshold crossing, lock the account
     * and/or block the IP, log a SecurityEvent, and notify admins. Never
     * re-extends an already-active lock/block - only a fresh failed attempt
     * after the previous one expires starts a new window, so a persistent
     * attacker can't keep the real account owner locked out indefinitely.
     *
     * Never throws - a failure here must not break the login response.
     */
    public function recordFailedAttempt(?object $authUser, string $ip, string $attemptedIdentifier): void
    {
        try {
            $this->checkAccountThreshold($authUser, $ip, $attemptedIdentifier);
        } catch (\Throwable $e) {
            Log::warning('LoginSecurityService: account threshold check failed', ['error' => $e->getMessage()]);
        }

        try {
            $this->checkIpThreshold($ip, $attemptedIdentifier);
        } catch (\Throwable $e) {
            Log::warning('LoginSecurityService: ip threshold check failed', ['error' => $e->getMessage()]);
        }
    }

    private function checkAccountThreshold(?object $authUser, string $ip, string $attemptedIdentifier): void
    {
        if (!$authUser || $this->isAccountLocked($authUser)) {
            return;
        }

        $isEmployee = !is_null($authUser->employee_id);
        $userId     = $isEmployee ? $authUser->employee_id : ($authUser->customer_id ?? 0);
        $userType   = $isEmployee ? 'employee' : 'customer';

        $cfg = config('security_center.brute_force.account');

        $recentFailures = LoginActivity::where('user_id', $userId)
            ->where('user_type', $userType)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($cfg['window_minutes']))
            ->count();

        if ($recentFailures < $cfg['max_attempts']) {
            return;
        }

        $lockedUntil = now()->addMinutes($cfg['lockout_minutes']);

        DB::table('auth_users')->where('id', $authUser->id)->update(['locked_until' => $lockedUntil]);

        $event = SecurityEvent::record([
            'event_type'         => 'brute_force_account_lockout',
            'severity'           => 'high',
            'module'             => 'Auth',
            'status'             => 'open',
            'title'              => "Account locked after {$recentFailures} failed login attempts",
            'description'        => "Account \"{$attemptedIdentifier}\" was auto-locked until {$lockedUntil->format('d M Y H:i')} after {$recentFailures} failed attempts in the last {$cfg['window_minutes']} minutes.",
            'target_employee_id' => $isEmployee ? $userId : null,
            'target_identifier'  => $attemptedIdentifier,
            'target_ip'          => $ip,
            'ip_address'         => $ip,
            'payload'            => [
                'auth_user_id' => $authUser->id,
                'attempts'     => $recentFailures,
                'locked_until' => $lockedUntil->toDateTimeString(),
            ],
        ]);

        if ($event) {
            $this->notifyAdmins($event, "Account \"{$attemptedIdentifier}\" locked after repeated failed logins");
        }
    }

    private function checkIpThreshold(string $ip, string $attemptedIdentifier): void
    {
        if ($this->isIpBlocked($ip)) {
            return;
        }

        // Credential stuffing / password spray: many DISTINCT accounts
        // failing from this IP. Checked first and independently of the
        // raw-attempt threshold below - a spray can stay well under that
        // volume (e.g. 5 accounts tried once each) while still being a
        // clear coordinated-attack signal, and it's the more specific,
        // more actionable diagnosis when both conditions happen to hold.
        $stuffingCfg = config('security_center.brute_force.credential_stuffing');

        $distinctAccounts = (int) LoginActivity::where('ip_address', $ip)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($stuffingCfg['window_minutes']))
            ->distinct('user_name')
            ->count('user_name');

        if ($distinctAccounts >= $stuffingCfg['distinct_accounts_threshold']) {
            $this->blockIpAndRecord(
                ip: $ip,
                lockoutMinutes: $stuffingCfg['lockout_minutes'],
                eventType: 'credential_stuffing',
                severity: 'critical',
                title: "IP blocked - credential stuffing ({$distinctAccounts} accounts)",
                description: "IP {$ip} attempted logins against {$distinctAccounts} distinct accounts within {$stuffingCfg['window_minutes']} minutes (last target: \"{$attemptedIdentifier}\") - consistent with credential stuffing or password spraying using a leaked credential list.",
                payload: ['distinct_accounts' => $distinctAccounts],
                notifyPreview: "Credential stuffing detected from {$ip} ({$distinctAccounts} accounts)"
            );

            return;
        }

        $ipCfg = config('security_center.brute_force.ip');

        $recentFailures = LoginActivity::where('ip_address', $ip)
            ->where('status', 'failed')
            ->where('created_at', '>=', now()->subMinutes($ipCfg['window_minutes']))
            ->count();

        if ($recentFailures < $ipCfg['max_attempts']) {
            return;
        }

        $this->blockIpAndRecord(
            ip: $ip,
            lockoutMinutes: $ipCfg['lockout_minutes'],
            eventType: 'brute_force_ip_lockout',
            severity: 'high',
            title: "IP blocked after {$recentFailures} failed login attempts",
            description: "IP {$ip} was auto-blocked after {$recentFailures} failed login attempts in the last {$ipCfg['window_minutes']} minutes (last target: \"{$attemptedIdentifier}\").",
            payload: ['attempts' => $recentFailures],
            notifyPreview: "IP {$ip} blocked after repeated failed logins"
        );
    }

    private function blockIpAndRecord(
        string $ip,
        int $lockoutMinutes,
        string $eventType,
        string $severity,
        string $title,
        string $description,
        array $payload,
        string $notifyPreview
    ): void {
        $expiresAt = now()->addMinutes($lockoutMinutes);

        DB::table('blocked_ips')->updateOrInsert(
            ['ip_address' => $ip],
            [
                'reason'     => 'Automated: ' . str_replace('_', ' ', $eventType),
                'source'     => 'auto',
                'expires_at' => $expiresAt,
                'created_at' => now(),
            ]
        );

        $event = SecurityEvent::record([
            'event_type'  => $eventType,
            'severity'    => $severity,
            'module'      => 'Auth',
            'status'      => 'open',
            'title'       => $title,
            'description' => "{$description} Blocked until {$expiresAt->format('d M Y H:i')}.",
            'target_ip'   => $ip,
            'ip_address'  => $ip,
            'payload'     => $payload + ['expires_at' => $expiresAt->toDateTimeString()],
        ]);

        if ($event) {
            $this->notifyAdmins($event, $notifyPreview);
        }
    }

    /**
     * Notify every EC Administrator via the existing in-app notification
     * pipeline (Notification::create -> WebPushService, automatic).
     */
    public function notifyAdmins(SecurityEvent $event, string $preview): void
    {
        $adminIds = DB::table('employee_role_assignment as era')
            ->join('employee_role as er', 'era.role_id', '=', 'er.id')
            ->where('er.id', RoleId::EC_ADMINISTRATOR->value)
            ->pluck('era.employee_id');

        foreach ($adminIds as $adminId) {
            Notification::create([
                'employee_id' => $adminId,
                'type'        => 'security_alert',
                'from_name'   => 'Security Center',
                'preview'     => $preview,
                'link'        => route('admin.security-center'),
                'is_read'     => false,
            ]);
        }
    }
}
