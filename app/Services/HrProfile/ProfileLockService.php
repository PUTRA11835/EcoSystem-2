<?php

namespace App\Services\HrProfile;

use App\Models\EmployeeHistory;
use App\Models\EmployeeHrProfile;
use App\Services\Onboarding\OnboardingProgressService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci profil (H3.11; HC-D29). Aturan murni ada di ProfileLockPolicy; di sini sisi database + riwayat.
 *
 *  - lock   : hanya bila progres Onboarding 100%; HR menekan eksplisit; tercatat di riwayat karyawan.
 *  - unlock : alasan wajib (min. 5 karakter); tercatat; setelah diperbaiki HR mengunci lagi.
 *  - isLocked : dipakai middleware di SETIAP penyimpanan seksi oleh pemilik → TIDAK pernah melempar galat
 *    (bila tabel belum ada atau kueri gagal: dianggap tidak terkunci, agar rilis kode sebelum migrasi tidak
 *    memblokir penyimpanan semua pegawai).
 */
class ProfileLockService
{
    public function __construct(private readonly OnboardingProgressService $progress)
    {
    }

    public function isLocked(int $employeeId): bool
    {
        try {
            if ($employeeId <= 0 || !Schema::hasTable('employee_hr_profile')) {
                return false;
            }

            return DB::table('employee_hr_profile')->where('employee_id', $employeeId)->whereNotNull('locked_at')->exists();
        } catch (\Throwable $e) {
            Log::error('Profile lock: gagal memeriksa status kunci', ['employee_id' => $employeeId, 'error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * @return array{ok: bool, code: string, message: string}
     */
    public function lock(int $employeeId, int $actorId): array
    {
        $p = $this->progress->forEmployee($employeeId);
        if ($p === null) {
            return $this->fail('not_found', 'Employee not found or not active.');
        }
        if (!ProfileLockPolicy::canLock($p['status'])) {
            $left = $p['total'] - $p['done'];

            return $this->fail('incomplete', "Cannot lock yet: {$left} required item(s) are still missing (" . $p['done'] . ' of ' . $p['total'] . ' filled).');
        }

        $result = DB::transaction(function () use ($employeeId, $actorId) {
            $profile = EmployeeHrProfile::where('employee_id', $employeeId)->lockForUpdate()->first()
                ?: new EmployeeHrProfile(['employee_id' => $employeeId]);

            if ($profile->locked_at !== null) {
                return false;
            }
            $profile->forceFill(['locked_at' => now(), 'locked_by' => $actorId, 'updated_by' => $actorId])->save();
            $this->history($employeeId, $actorId, 'Profile locked', ProfileLockPolicy::historyDescription('lock'));

            return true;
        });

        return $result ? ['ok' => true, 'code' => 'ok', 'message' => 'Profile verified and locked.'] : $this->fail('already_locked', 'This profile is already locked.');
    }

    /**
     * @return array{ok: bool, code: string, message: string}
     */
    public function unlock(int $employeeId, int $actorId, ?string $reason): array
    {
        if (!ProfileLockPolicy::validUnlockReason($reason)) {
            return $this->fail('reason_required', 'Please give a reason (at least 5 characters). It is recorded in the employee history.');
        }

        $result = DB::transaction(function () use ($employeeId, $actorId, $reason) {
            $profile = EmployeeHrProfile::where('employee_id', $employeeId)->lockForUpdate()->first();
            if (!$profile || $profile->locked_at === null) {
                return false;
            }
            $profile->forceFill(['locked_at' => null, 'locked_by' => null, 'updated_by' => $actorId])->save();
            $this->history($employeeId, $actorId, 'Profile unlocked', ProfileLockPolicy::historyDescription('unlock', $reason));

            return true;
        });

        return $result ? ['ok' => true, 'code' => 'ok', 'message' => 'Profile unlocked. Remember to lock it again after the changes.'] : $this->fail('not_locked', 'This profile is not locked.');
    }

    /** @return array{ok: false, code: string, message: string} */
    private function fail(string $code, string $message): array
    {
        return ['ok' => false, 'code' => $code, 'message' => $message];
    }

    private function history(int $employeeId, int $actorId, string $action, string $description): void
    {
        EmployeeHistory::create([
            'employee_id'  => $employeeId,
            'action'       => $action,
            'description'  => $description,
            'performed_by' => $actorId,
            'performed_at' => now(),
        ]);
    }
}
