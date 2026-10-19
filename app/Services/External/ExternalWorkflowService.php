<?php

namespace App\Services\External;

use App\Models\Notification;
use App\Support\ApprovalRecipients;
use App\Support\External\ExternalTimesheetRules as R;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pengaturan penyetuju seksi External → Timesheet & Payment dan penentuan siapa boleh bertindak.
 *
 * Dua tahap: persetujuan lembar jam (bawaan: Delivery RPMO Head) dan verifikasi invoice (bawaan: role HO Finance). Tiap tahap punya
 * langkah (role atau karyawan tertentu — SIAPA PUN yang terdaftar boleh bertindak) dan boleh punya PENGECUALIAN per konsultan yang
 * menimpa langkah bawaan. Hasil resolusi dibekukan per lembar saat Submit (external_timesheet_approvals).
 */
class ExternalWorkflowService
{
    public function settings(): object
    {
        return DB::table('external_workflow_settings')->orderBy('id')->first()
            ?? (object) ['allow_self_approval' => 0, 'backfill_days' => 60];
    }

    /** @return \Illuminate\Support\Collection<int,object> */
    public function steps(string $stage)
    {
        return DB::table('external_workflow_steps')->where('stage', $stage)->orderBy('order_seq')->orderBy('id')->get()->map(function ($s) {
            $s->role_ids = json_decode($s->approver_role_ids ?? '[]', true) ?: [];
            $s->employee_ids = json_decode($s->approver_employee_ids ?? '[]', true) ?: [];

            return $s;
        });
    }

    /** @return \Illuminate\Support\Collection<int,object> pengecualian per konsultan, lengkap dengan nama */
    public function overrides()
    {
        $rows = DB::table('external_approver_overrides as o')
            ->join('employee as e', 'e.employee_id', '=', 'o.employee_id')
            ->leftJoin('employee_basic_data as b', 'b.employee_id', '=', 'o.employee_id')
            ->orderBy('b.first_name')->get(['o.*', 'e.eci', 'b.first_name', 'b.last_name']);
        $names = $this->names($rows->flatMap(fn ($r) => json_decode($r->approver_employee_ids, true) ?: [])->unique()->all());

        return $rows->map(function ($r) use ($names) {
            $r->name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: $r->eci;
            $r->employee_ids = json_decode($r->approver_employee_ids, true) ?: [];
            $r->approver_names = array_map(fn ($id) => $names[$id] ?? ('#' . $id), $r->employee_ids);

            return $r;
        });
    }

    /**
     * Siapa boleh bertindak pada tahap ini untuk konsultan ini: pengecualian bila ada, jika tidak gabungan semua langkah.
     *
     * @return array{ids:int[],from:string}
     */
    public function resolveApprovers(string $stage, int $consultantId): array
    {
        $override = DB::table('external_approver_overrides')->where('employee_id', $consultantId)->where('stage', $stage)->first();
        if ($override) {
            return ['ids' => array_values(array_unique(array_map('intval', json_decode($override->approver_employee_ids, true) ?: []))), 'from' => 'override'];
        }

        $ids = [];
        $from = 'role';
        foreach ($this->steps($stage) as $step) {
            foreach ($step->role_ids as $roleId) {
                $ids = array_merge($ids, ApprovalRecipients::forStep((object) ['approver_type' => 'role', 'approver_role_id' => (int) $roleId]));
            }
            if ($step->approver_type === 'employee' || $step->employee_ids) {
                $ids = array_merge($ids, array_map('intval', $step->employee_ids));
                $from = 'employee';
            }
        }

        return ['ids' => array_values(array_unique($ids)), 'from' => $from];
    }

    /** Penyetuju yang dibekukan pada lembar (bila belum ada, resolusi sekarang). @return int[] */
    public function approversOf(int $sheetId, string $stage, int $consultantId): array
    {
        $row = DB::table('external_timesheet_approvals')->where('timesheet_id', $sheetId)->where('stage', $stage)->first();

        return $row ? array_map('intval', json_decode($row->approver_employee_ids, true) ?: []) : $this->resolveApprovers($stage, $consultantId)['ids'];
    }

    /** Membekukan penyetuju kedua tahap pada lembar (dipanggil saat Submit). */
    public function snapshot(int $sheetId, int $consultantId): void
    {
        foreach (array_keys(R::STAGES) as $stage) {
            $r = $this->resolveApprovers($stage, $consultantId);
            DB::table('external_timesheet_approvals')->updateOrInsert(['timesheet_id' => $sheetId, 'stage' => $stage], [
                'approver_employee_ids' => json_encode($r['ids']), 'resolved_from' => $r['from'], 'updated_at' => now(), 'created_at' => now(),
            ]);
        }
    }

    /**
     * Apakah $actorId boleh menyetujui/memverifikasi lembar ini pada tahap tersebut. Penyetuju tidak boleh bertindak atas lembarnya
     * sendiri kecuali pengaturan mengizinkan. Bila tak seorang pun terdaftar sebagai penyetuju, `$fallback` (mis. pemegang kotak Edit)
     * menentukan — supaya alur tidak buntu.
     */
    public function canAct(int $sheetId, string $stage, int $consultantId, int $actorId, bool $fallback = false): bool
    {
        if ($actorId === $consultantId && !$this->settings()->allow_self_approval) {
            return false;
        }
        $ids = $this->approversOf($sheetId, $stage, $consultantId);

        return $ids === [] ? $fallback : in_array($actorId, $ids, true);
    }

    // ── Pemberitahuan ─────────────────────────────────────────────────────

    /** @param  int[]  $employeeIds */
    public function notify(array $employeeIds, string $type, string $message, string $link, ?int $fromId = null, ?int $exceptId = null): void
    {
        foreach (array_unique($employeeIds) as $id) {
            if ($exceptId !== null && (int) $id === $exceptId) {
                continue;
            }
            try {
                Notification::create([
                    'employee_id' => (int) $id, 'type' => $type, 'from_employee_id' => $fromId,
                    'preview' => mb_strimwidth($message, 0, 250, '…'), 'link' => $link,
                ]);
            } catch (\Throwable $e) {
                Log::error('External workflow: notification failed', ['type' => $type, 'to' => $id, 'error' => $e->getMessage()]);
            }
        }
    }

    // ── Tulis pengaturan (halaman Settings) ───────────────────────────────

    /** @throws \DomainException */
    public function saveStep(?int $id, string $stage, string $name, string $type, array $roleIds, array $employeeIds): void
    {
        if (!isset(R::STAGES[$stage])) {
            throw new \DomainException('Choose a valid stage.');
        }
        $name = trim($name);
        if ($name === '') {
            throw new \DomainException('Give the step a name.');
        }
        $roleIds = $type === 'role' ? array_values(array_unique(array_map('intval', $roleIds))) : [];
        $employeeIds = $type === 'employee' ? array_values(array_unique(array_map('intval', $employeeIds))) : [];
        if ($type === 'role' && !$roleIds) {
            throw new \DomainException('Pick at least one role.');
        }
        if ($type === 'employee' && !$employeeIds) {
            throw new \DomainException('Pick at least one person.');
        }

        $row = [
            'stage' => $stage, 'name' => mb_substr($name, 0, 120), 'approver_type' => $type,
            'approver_role_ids' => json_encode($roleIds), 'approver_employee_ids' => json_encode($employeeIds), 'updated_at' => now(),
        ];
        if ($id) {
            DB::table('external_workflow_steps')->where('id', $id)->update($row);

            return;
        }
        DB::table('external_workflow_steps')->insert($row + [
            'order_seq' => (int) DB::table('external_workflow_steps')->where('stage', $stage)->max('order_seq') + 1, 'created_at' => now(),
        ]);
    }

    /** @throws \DomainException */
    public function deleteStep(int $id): void
    {
        $step = DB::table('external_workflow_steps')->where('id', $id)->first();
        if (!$step) {
            return;
        }
        if (DB::table('external_workflow_steps')->where('stage', $step->stage)->count() <= 1) {
            throw new \DomainException('Each stage needs at least one step. Edit this one instead, or add another first.');
        }
        DB::table('external_workflow_steps')->where('id', $id)->delete();
    }

    /** @param  int[]  $approverIds @throws \DomainException */
    public function saveOverride(int $consultantId, string $stage, array $approverIds, int $actorId): void
    {
        if (!isset(R::STAGES[$stage])) {
            throw new \DomainException('Choose a valid stage.');
        }
        if (!DB::table('employee_basic_data')->where('employee_id', $consultantId)->where('employee_type', 'External')->exists()) {
            throw new \DomainException('Pick an external consultant.');
        }
        $approverIds = array_values(array_unique(array_map('intval', $approverIds)));
        $approverIds = array_values(array_diff($approverIds, [$consultantId]));
        if (!$approverIds) {
            throw new \DomainException('Pick at least one approver (not the consultant).');
        }
        DB::table('external_approver_overrides')->updateOrInsert(['employee_id' => $consultantId, 'stage' => $stage], [
            'approver_employee_ids' => json_encode($approverIds), 'created_by' => $actorId, 'updated_at' => now(), 'created_at' => now(),
        ]);
    }

    public function deleteOverride(int $id): void
    {
        DB::table('external_approver_overrides')->where('id', $id)->delete();
    }

    public function saveOptions(bool $selfApproval, int $backfillDays): void
    {
        $row = ['allow_self_approval' => $selfApproval,
            'backfill_days' => max(0, min(365, $backfillDays)), 'updated_at' => now()];
        $id = DB::table('external_workflow_settings')->orderBy('id')->value('id');
        $id ? DB::table('external_workflow_settings')->where('id', $id)->update($row) : DB::table('external_workflow_settings')->insert($row + ['created_at' => now()]);
    }

    /** @param  int[]  $ids  @return array<int,string> */
    public function names(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return DB::table('employee_basic_data')->whereIn('employee_id', $ids)
            ->selectRaw("employee_id, CONCAT_WS(' ', first_name, last_name) as n")->pluck('n', 'employee_id')
            ->map(fn ($n) => trim((string) $n))->all();
    }

    /** Pilihan untuk formulir Settings: role dan karyawan aktif. */
    public function options(): array
    {
        return [
            'roles' => DB::table('employee_role')->orderBy('name')->get(['id', 'name']),
            'people' => DB::table('employee as e')->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
                ->where('e.is_active', 1)->orderBy('b.first_name')
                ->get(['e.employee_id as id', 'e.eci', 'b.first_name', 'b.last_name'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'name' => trim($p->first_name . ' ' . $p->last_name) . ' (' . $p->eci . ')'])->all(),
        ];
    }
}
