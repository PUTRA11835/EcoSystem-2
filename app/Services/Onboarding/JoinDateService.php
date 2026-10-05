<?php

namespace App\Services\Onboarding;

use App\Models\AuditLog;
use App\Models\EmployeeHistory;
use App\Models\Notification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Alat join date untuk HR (HC-D64) — sisi database. Aturan murni ada di JoinDateRules.
 *
 * Join date (= employee_basic_data.since_date) dikunci dari pegawai (HC-D62); karyawan baru dari offering sudah terisi
 * otomatis. Untuk data LAMA yang kosong, HR memakai tiga alat ini (opsional):
 *   - setDates : isi tanggal (satu per satu atau massal). HANYA mengisi yang masih KOSONG — tidak pernah menimpa tanggal
 *                yang sudah ada (mengubahnya tetap lewat Master › Employee). Tiap isian tercatat di riwayat karyawan + audit.
 *   - preview  : pratinjau impor CSV/tempel (ECI, tanggal) sebelum diterapkan; tak menulis apa pun.
 *   - remind   : pengingat lewat bel notifikasi ke karyawan yang belum punya join date (maks. sekali per 7 hari).
 */
class JoinDateService
{
    public const REMINDER_TYPE = 'join_date_reminder';
    public const REMINDER_COOLDOWN_DAYS = 7;
    public const CACHE_KEY = 'cc_onboarding_attention'; // penanda HR di Command Center — dibuang agar angkanya langsung benar

    /**
     * Pratinjau impor. Setiap baris diberi status: ok | unknown_eci | inactive | already_set | duplicate | invalid_date |
     * missing_eci | missing_date | too_old | in_future.
     *
     * @return array{rows: array, summary: array<string,int>}
     */
    public function preview(string $csvText): array
    {
        $parsed = JoinDateRules::parseCsv($csvText);
        $ecis = array_values(array_unique(array_filter(array_map(fn ($r) => mb_strtolower($r['eci']), $parsed))));

        $emp = [];
        foreach (array_chunk($ecis, 500) as $chunk) {
            $q = DB::table('employee as e')
                ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
                ->whereIn(DB::raw('LOWER(e.eci)'), $chunk)
                ->get(['e.employee_id', 'e.eci', 'e.is_active', 'b.first_name', 'b.last_name', 'b.since_date', 'b.deletion_flag']);
            foreach ($q as $r) {
                $emp[mb_strtolower($r->eci)] = $r;
            }
        }

        $seen = [];
        $out = [];
        $summary = ['ok' => 0, 'skipped' => 0, 'errors' => 0, 'total' => 0];
        foreach ($parsed as $row) {
            $status = $row['error'] ?? 'ok';
            $name = null;
            $current = null;
            $employeeId = null;
            if ($status === 'ok') {
                $key = mb_strtolower($row['eci']);
                $e = $emp[$key] ?? null;
                if ($e === null) {
                    $status = 'unknown_eci';
                } elseif (!$e->is_active || $e->deletion_flag) {
                    $status = 'inactive';
                } elseif (isset($seen[$key])) {
                    $status = 'duplicate';
                } elseif ($e->since_date !== null) {
                    $status = 'already_set';
                } else {
                    $seen[$key] = true;
                }
                if ($e !== null) {
                    $employeeId = (int) $e->employee_id;
                    $name = trim(($e->first_name ?? '') . ' ' . ($e->last_name ?? ''));
                    $current = $e->since_date ? substr((string) $e->since_date, 0, 10) : null;
                }
            }
            $out[] = $row + ['status' => $status, 'employee_id' => $employeeId, 'name' => $name, 'current' => $current];
            $summary['total']++;
            if ($status === 'ok') {
                $summary['ok']++;
            } elseif (in_array($status, ['already_set', 'duplicate'], true)) {
                $summary['skipped']++;
            } else {
                $summary['errors']++;
            }
        }

        return ['rows' => $out, 'summary' => $summary];
    }

    /**
     * Isi join date. $items = [['employee_id' => int, 'date' => 'Y-m-d|format lain'], …].
     * Tanggal yang sudah terisi dilewati (tak pernah ditimpa); seluruh penulisan satu transaksi.
     *
     * @return array{set: int, skipped: array<int, array{employee_id:int, reason:string}>}
     */
    public function setDates(array $items, int $actorId, string $source = 'manual'): array
    {
        $set = 0;
        $skipped = [];
        DB::transaction(function () use ($items, $actorId, $source, &$set, &$skipped) {
            foreach ($items as $item) {
                $id = (int) ($item['employee_id'] ?? 0);
                $iso = JoinDateRules::parseDate((string) ($item['date'] ?? ''));
                if ($id <= 0 || $iso === null) {
                    $skipped[] = ['employee_id' => $id, 'reason' => 'invalid_date'];
                    continue;
                }
                if (JoinDateRules::rangeError($iso) !== null) {
                    $skipped[] = ['employee_id' => $id, 'reason' => JoinDateRules::rangeError($iso)];
                    continue;
                }
                $row = DB::table('employee_basic_data')->where('employee_id', $id)->lockForUpdate()->first();
                if (!$row || $row->deletion_flag) {
                    $skipped[] = ['employee_id' => $id, 'reason' => 'not_found'];
                    continue;
                }
                if ($row->since_date !== null) {
                    $skipped[] = ['employee_id' => $id, 'reason' => 'already_set'];
                    continue;
                }

                DB::table('employee_basic_data')->where('employee_id', $id)->update([
                    'since_date'      => $iso,
                    'last_changed_by' => session('user.eci', 'HR'),
                    'last_changed_on' => now(),
                ]);
                EmployeeHistory::create([
                    'employee_id'  => $id,
                    'action'       => 'join_date_set',
                    'description'  => "Join date set to {$iso} by HR ({$source}).",
                    'performed_by' => $actorId ?: null,
                    'performed_at' => now(),
                ]);
                $this->audit((int) $row->basic_data_id, $id, $iso, $source);
                $set++;
            }
        });

        if ($set > 0) {
            Cache::forget(self::CACHE_KEY);
        }

        return ['set' => $set, 'skipped' => $skipped];
    }

    /**
     * Kirim pengingat ke karyawan yang belum punya join date. Dilewati bila sudah punya tanggal atau sudah diingatkan
     * dalam 7 hari terakhir (tak mengirim berulang).
     *
     * @param  int[]  $employeeIds
     * @return array{sent: int, skipped: array<int, array{employee_id:int, reason:string}>}
     */
    public function remind(array $employeeIds, int $actorId): array
    {
        $employeeIds = array_values(array_unique(array_map('intval', $employeeIds)));
        $sent = 0;
        $skipped = [];
        $rows = DB::table('employee_basic_data')->whereIn('employee_id', $employeeIds)->get(['employee_id', 'since_date', 'deletion_flag'])->keyBy('employee_id');
        $recent = DB::table('notifications')->where('type', self::REMINDER_TYPE)->whereIn('employee_id', $employeeIds)
            ->where('created_at', '>=', now()->subDays(self::REMINDER_COOLDOWN_DAYS))->pluck('employee_id')->flip();

        foreach ($employeeIds as $id) {
            $r = $rows->get($id);
            if (!$r || $r->deletion_flag) {
                $skipped[] = ['employee_id' => $id, 'reason' => 'not_found'];
            } elseif ($r->since_date !== null) {
                $skipped[] = ['employee_id' => $id, 'reason' => 'already_set'];
            } elseif ($recent->has($id)) {
                $skipped[] = ['employee_id' => $id, 'reason' => 'recently_reminded'];
            } else {
                Notification::create([
                    'employee_id'      => $id,
                    'type'             => self::REMINDER_TYPE,
                    'from_employee_id' => $actorId ?: null,
                    'from_name'        => 'HR',
                    'preview'          => 'HR does not have your join date yet. Please send a copy of your offer letter or contract to HR (you can upload it under My Profile › Attachment). This date is set by HR; you cannot edit it yourself.',
                    'link'             => '/my-profile?section=attachment',
                    'is_read'          => false,
                ]);
                $sent++;
            }
        }

        return ['sent' => $sent, 'skipped' => $skipped];
    }

    private function audit(int $basicDataId, int $employeeId, string $iso, string $source): void
    {
        try {
            AuditLog::recordAction(
                module: 'Employee',
                auditableType: 'App\\Models\\EmployeeBasicData',
                auditableId: $basicDataId,
                event: 'updated',
                recordLabel: 'Employee #' . $employeeId,
                description: "set join date (Since Date) to {$iso} via HR join-date tools ({$source})",
                old: ['since_date' => null],
                new: ['since_date' => $iso],
            );
        } catch (\Throwable $e) {
            Log::warning('Join date: audit log gagal ditulis', ['employee_id' => $employeeId, 'error' => $e->getMessage()]);
        }
    }
}
