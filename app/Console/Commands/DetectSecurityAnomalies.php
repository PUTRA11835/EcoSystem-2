<?php

namespace App\Console\Commands;

use App\Enums\RoleId;
use App\Models\AuditLog;
use App\Models\LoginActivity;
use App\Models\SecurityEvent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Correlation-based security signals that a single request can't decide on
 * its own: privilege escalation and mass export (read from audit_logs,
 * written there by EmployeeController::changeRole() and the export methods
 * on AdminBackupController/TicketMigrationController), and anomalous logins
 * (read from login_activity). Runs on a schedule rather than at request
 * time since none of these need to block anything.
 *
 * Each detector tracks its own high-water mark in cache so re-runs only
 * scan rows created since the last run, rather than the full table.
 */
class DetectSecurityAnomalies extends Command
{
    protected $signature = 'security:detect-anomalies';

    protected $description = 'Scan recent audit_logs/login_activity for privilege escalation, mass export, and anomalous logins.';

    private const PRIV_MARK_KEY   = 'security_anomaly:last_audit_log_id_priv';
    private const EXPORT_MARK_KEY = 'security_anomaly:last_audit_log_id_export';
    private const LOGIN_MARK_KEY  = 'security_anomaly:last_login_activity_id';

    public function handle(): int
    {
        $flagged = 0;
        $flagged += $this->detectPrivilegeEscalation();
        $flagged += $this->detectMassExport();
        $flagged += $this->detectAnomalousLogins();

        $this->info("Anomaly scan complete. Flagged: {$flagged}");
        Log::info('DetectSecurityAnomalies completed', ['flagged' => $flagged]);

        return self::SUCCESS;
    }

    private function detectPrivilegeEscalation(): int
    {
        $lastId      = (int) Cache::get(self::PRIV_MARK_KEY, 0);
        $adminRoleId = RoleId::EC_ADMINISTRATOR->value;
        $flagged     = 0;
        $maxId       = $lastId;

        AuditLog::where('module', 'Employee Role')
            ->where('event', 'updated')
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$flagged, &$maxId, $adminRoleId) {
                foreach ($rows as $row) {
                    $maxId = max($maxId, $row->id);

                    $oldRoleIds = $row->old_values['role_ids'] ?? [];
                    $newRoleIds = $row->new_values['role_ids'] ?? [];

                    $gainedAdmin = in_array($adminRoleId, $newRoleIds, true) && !in_array($adminRoleId, $oldRoleIds, true);
                    if (!$gainedAdmin || SecurityEvent::where('related_audit_log_id', $row->id)->exists()) {
                        continue;
                    }

                    $selfEscalation = $row->actor_id && (int) $row->actor_id === (int) $row->auditable_id;

                    SecurityEvent::record([
                        'event_type'           => 'privilege_escalation',
                        'severity'             => $selfEscalation ? 'critical' : 'high',
                        'module'               => 'Privilege',
                        'status'               => 'open',
                        'title'                => 'Employee granted EC Administrator role',
                        'description'          => ($row->actor_name ?? 'Someone') . ' granted EC Administrator to '
                            . ($row->record_label ?? "employee #{$row->auditable_id}")
                            . ($selfEscalation ? ' (self-escalation)' : ''),
                        'target_employee_id'   => $row->auditable_id,
                        'target_identifier'    => $row->record_label ?? "Employee #{$row->auditable_id}",
                        'actor_employee_id'    => $row->actor_id,
                        'actor_name'           => $row->actor_name,
                        'ip_address'           => $row->ip_address,
                        'related_audit_log_id' => $row->id,
                        'payload'              => ['old_role_ids' => $oldRoleIds, 'new_role_ids' => $newRoleIds],
                    ]);

                    $flagged++;
                }
            });

        Cache::forever(self::PRIV_MARK_KEY, $maxId);

        return $flagged;
    }

    private function detectMassExport(): int
    {
        $lastId         = (int) Cache::get(self::EXPORT_MARK_KEY, 0);
        $rowThreshold   = (int) config('security_center.anomaly.mass_export.row_count_threshold', 1000);
        $dailyThreshold = (int) config('security_center.anomaly.mass_export.exports_per_day_threshold', 3);
        $flagged        = 0;
        $maxId          = $lastId;

        AuditLog::where('module', 'Export')
            ->where('id', '>', $lastId)
            ->orderBy('id')
            ->chunk(200, function ($rows) use (&$flagged, &$maxId, $rowThreshold, $dailyThreshold) {
                foreach ($rows as $row) {
                    $maxId = max($maxId, $row->id);

                    if (SecurityEvent::where('related_audit_log_id', $row->id)->where('event_type', 'mass_export')->exists()) {
                        continue;
                    }

                    $rowCount = (int) ($row->new_values['row_count'] ?? 0);
                    $reasons  = [];

                    if ($rowCount >= $rowThreshold) {
                        $reasons[] = "single export of {$rowCount} rows (threshold {$rowThreshold})";
                    }

                    $recentCount = null;
                    if ($row->actor_id) {
                        $recentCount = AuditLog::where('module', 'Export')
                            ->where('actor_id', $row->actor_id)
                            ->where('created_at', '>=', now()->subDay())
                            ->count();

                        if ($recentCount >= $dailyThreshold) {
                            $reasons[] = "{$recentCount} exports by the same actor in the last 24h (threshold {$dailyThreshold})";
                        }
                    }

                    if (empty($reasons)) {
                        continue;
                    }

                    SecurityEvent::record([
                        'event_type'           => 'mass_export',
                        'severity'             => count($reasons) > 1 ? 'high' : 'medium',
                        'module'               => 'Export',
                        'status'               => 'open',
                        'title'                => 'Unusual data export activity',
                        'description'          => ($row->actor_name ?? 'Someone') . ' - ' . implode('; ', $reasons),
                        'actor_employee_id'    => $row->actor_id,
                        'actor_name'           => $row->actor_name,
                        'ip_address'           => $row->ip_address,
                        'related_audit_log_id' => $row->id,
                        'payload'              => [
                            'row_count'           => $rowCount,
                            'recent_export_count' => $recentCount,
                            'auditable_type'      => $row->auditable_type,
                        ],
                    ]);

                    $flagged++;
                }
            });

        Cache::forever(self::EXPORT_MARK_KEY, $maxId);

        return $flagged;
    }

    private function detectAnomalousLogins(): int
    {
        $lastId  = (int) Cache::get(self::LOGIN_MARK_KEY, 0);
        $flagged = 0;
        $maxId   = $lastId;

        LoginActivity::where('status', 'success')
            ->where('user_type', 'employee')
            ->where('activity_id', '>', $lastId)
            ->orderBy('activity_id')
            ->chunk(200, function ($rows) use (&$flagged, &$maxId) {
                foreach ($rows as $row) {
                    $maxId = max($maxId, $row->activity_id);

                    $country = $row->location_country;
                    if (!$country || $country === 'Local') {
                        continue;
                    }

                    $knownCountries = LoginActivity::where('user_id', $row->user_id)
                        ->where('user_type', 'employee')
                        ->where('status', 'success')
                        ->where('activity_id', '<', $row->activity_id)
                        ->whereNotNull('location_country')
                        ->where('location_country', '!=', 'Local')
                        ->distinct()
                        ->pluck('location_country');

                    // No baseline yet (first tracked login), or matches known history.
                    if ($knownCountries->isEmpty() || $knownCountries->contains($country)) {
                        continue;
                    }

                    SecurityEvent::record([
                        'event_type'          => 'anomalous_login',
                        'severity'            => 'medium',
                        'module'              => 'Auth',
                        'status'              => 'open',
                        'title'               => "Login from a new country: {$country}",
                        'description'         => ($row->user_name ?? 'Employee') . " logged in from {$country}, not seen in their previous logins ("
                            . $knownCountries->implode(', ') . ').',
                        'target_employee_id'  => $row->user_id,
                        'target_identifier'   => $row->user_name ?? "Employee #{$row->user_id}",
                        'target_ip'           => $row->ip_address,
                        'ip_address'          => $row->ip_address,
                        'payload'             => ['country' => $country, 'known_countries' => $knownCountries->values()->all()],
                    ]);

                    $flagged++;
                }
            });

        Cache::forever(self::LOGIN_MARK_KEY, $maxId);

        return $flagged;
    }
}
