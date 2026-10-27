<?php

namespace App\Services\Offboarding;

use App\Models\EmployeeContract;
use App\Models\Notification;
use App\Services\Legal\LegalBasisService;
use App\Support\Contracts\ContractRules;
use App\Support\Offboarding\OffboardingSettlementRules as R;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * HR & General → Offboarding: proses keluarnya karyawan / konsultan.
 *
 *   dibuat → 6 tugas (templat di Settings) → [Final Settlement, Internal saja] → akses dinonaktifkan pada/ sesudah tanggal efektif
 *   → Complete (kontrak ditutup, engagement External diakhiri). Atau Cancel selama akses belum dinonaktifkan.
 *
 * Seluruhnya dikendalikan saklar di `offboarding_settings` (modul, kalkulator, otomatis-nonaktifkan, sertakan External). Penonaktifan
 * akses = employee.is_active = 0, auth_users.is_active = 0, token ingat-saya & langganan push dicabut; role dipertahankan (riwayat).
 * TIDAK memakai hapus-keras dan tidak menyentuh `deletion_flag`. Sesi browser yang sedang terbuka berakhir menurut masa sesi aplikasi.
 */
class OffboardingService
{
    public const TASK_ACCESS = 'deactivate_access';

    public function __construct(private LegalBasisService $legal) {}

    // ── Pengaturan ────────────────────────────────────────────────────────

    public function settings(): object
    {
        return DB::table('offboarding_settings')->orderBy('id')->first() ?? (object) [
            'module_enabled' => 0, 'calculator_enabled' => 0, 'auto_deactivate' => 0, 'include_external' => 1,
            'require_tasks_before_deactivate' => 1, 'allow_force_deactivate' => 0, 'reminder_days' => 3, 'leave_divisor' => 21,
        ];
    }

    public function enabled(): bool
    {
        return (bool) $this->settings()->module_enabled;
    }

    /**
     * @param  array{module_enabled:bool,calculator_enabled:bool,auto_deactivate:bool,include_external:bool,require_tasks_before_deactivate:bool,reminder_days:int,leave_divisor:float}  $d
     * @return string[] pesan peringatan (mis. kalkulator tidak dapat dinyalakan)
     */
    public function saveSettings(array $d): array
    {
        $warnings = [];
        $calculator = (bool) $d['calculator_enabled'];
        if ($calculator) {
            $missing = $this->legal->blockers('offboarding');
            if ($missing) {
                $calculator = false;
                $warnings[] = 'The calculator stays OFF: these legal-basis entries are not reviewed yet — ' . implode(', ', $missing) . '. Review them in Legal Basis first.';
            }
        }
        $row = [
            'module_enabled' => (bool) $d['module_enabled'], 'calculator_enabled' => $calculator, 'auto_deactivate' => (bool) $d['auto_deactivate'],
            'include_external' => (bool) $d['include_external'], 'require_tasks_before_deactivate' => (bool) $d['require_tasks_before_deactivate'],
            'reminder_days' => max(0, min(30, (int) $d['reminder_days'])), 'leave_divisor' => max(1, min(31, (float) $d['leave_divisor'])), 'updated_at' => now(),
        ];
        // Kolom baru: dilewati bila migrasinya belum dijalankan (mis. server yang baru menerima berkas kode).
        if (Schema::hasColumn('offboarding_settings', 'allow_force_deactivate')) {
            $row['allow_force_deactivate'] = (bool) ($d['allow_force_deactivate'] ?? false);
        } elseif (!empty($d['allow_force_deactivate'])) {
            $warnings[] = 'Force deactivate could not be saved: the database column allow_force_deactivate does not exist yet. Ask the administrator to add it (see the deployment notes).';
        }
        $id = DB::table('offboarding_settings')->orderBy('id')->value('id');
        $id ? DB::table('offboarding_settings')->where('id', $id)->update($row) : DB::table('offboarding_settings')->insert($row + ['created_at' => now()]);

        return $warnings;
    }

    public function calculatorReady(): bool
    {
        $s = $this->settings();

        return (bool) $s->module_enabled && (bool) $s->calculator_enabled && $this->legal->blockers('offboarding') === [];
    }

    // ── Templat tugas (Settings) ──────────────────────────────────────────

    /** @return \Illuminate\Support\Collection<int,object> */
    public function templates()
    {
        return DB::table('offboarding_task_templates')->orderBy('sort_order')->orderBy('id')->get();
    }

    /** @throws \DomainException */
    public function saveTemplate(?int $id, array $d): void
    {
        $name = trim((string) $d['name']);
        if ($name === '') {
            throw new \DomainException('Give the task a name.');
        }
        $row = [
            'name' => mb_substr($name, 0, 150), 'description' => ($d['description'] ?? '') !== '' ? mb_substr((string) $d['description'], 0, 500) : null,
            'due_offset_days' => max(-365, min(365, (int) ($d['due_offset_days'] ?? 0))), 'applies_to' => in_array($d['applies_to'] ?? 'both', ['internal', 'external', 'both'], true) ? $d['applies_to'] : 'both',
            'is_active' => !empty($d['is_active']), 'updated_at' => now(),
        ];
        if ($id) {
            DB::table('offboarding_task_templates')->where('id', $id)->update($row);

            return;
        }
        $key = 'custom_' . substr(md5($name . microtime()), 0, 8);
        DB::table('offboarding_task_templates')->insert($row + ['task_key' => $key, 'sort_order' => (int) DB::table('offboarding_task_templates')->max('sort_order') + 1, 'created_at' => now()]);
    }

    /** @throws \DomainException */
    public function deleteTemplate(int $id): void
    {
        $t = DB::table('offboarding_task_templates')->where('id', $id)->first();
        if ($t && $t->task_key === self::TASK_ACCESS) {
            throw new \DomainException('“Deactivate system access” is part of the process and cannot be deleted. You can switch it off or rename it.');
        }
        DB::table('offboarding_task_templates')->where('id', $id)->delete();
    }

    // ── Membuat ───────────────────────────────────────────────────────────

    /** Karyawan aktif yang boleh diproses (tanpa Offboarding terbuka). @return array<int,array<string,mixed>> */
    public function eligibleEmployees(): array
    {
        $includeExternal = (bool) $this->settings()->include_external;

        return DB::table('employee as e')->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->where('e.is_active', 1)
            ->when(!$includeExternal, fn ($q) => $q->where('b.employee_type', '!=', 'External'))
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('offboardings as o')->whereColumn('o.employee_id', 'e.employee_id')->where('o.status', 'in_progress'))
            ->orderBy('b.first_name')->get(['e.employee_id', 'e.eci', 'b.first_name', 'b.last_name', 'b.position', 'b.employee_type'])
            ->map(fn ($r) => [
                'id' => (int) $r->employee_id, 'eci' => $r->eci, 'name' => trim($r->first_name . ' ' . $r->last_name) ?: $r->eci,
                'position' => $r->position, 'kind' => $r->employee_type === 'External' ? 'External' : 'Internal',
            ])->all();
    }

    /**
     * @param  array{employee_id:int,offboarding_type:string,reason_code?:?string,effective_date:string,last_working_day?:?string,notes?:?string}  $d
     * @throws \DomainException
     */
    public function create(array $d, int $actorId): int
    {
        if (!$this->enabled()) {
            throw new \DomainException('Offboarding is switched off in Offboarding Settings.');
        }
        $employeeId = (int) $d['employee_id'];
        $emp = DB::table('employee as e')->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')->where('e.employee_id', $employeeId)->first(['e.is_active', 'b.employee_type']);
        if (!$emp || !$emp->is_active) {
            throw new \DomainException('Pick an active employee.');
        }
        $kind = $emp->employee_type === 'External' ? 'External' : 'Internal';
        if ($kind === 'External' && !$this->settings()->include_external) {
            throw new \DomainException('External consultants are not included in Offboarding (see Offboarding Settings).');
        }
        if (DB::table('offboardings')->where('employee_id', $employeeId)->where('status', 'in_progress')->exists()) {
            throw new \DomainException('This person already has an offboarding in progress.');
        }
        $type = (string) $d['offboarding_type'];
        if (!in_array($type, R::TYPES_BY_KIND[$kind], true)) {
            throw new \DomainException("“" . (R::TYPES[$type] ?? $type) . "” does not apply to " . ($kind === 'External' ? 'an external consultant' : 'an internal employee') . '.');
        }
        $reason = R::reasonFor($type, $d['reason_code'] ?? null);
        if ($type === 'termination' && $kind === 'Internal' && $reason === null) {
            throw new \DomainException('Choose the reason for the termination.');
        }
        $contract = $this->runningContract($employeeId);
        if ($type === 'end_of_pkwt' && ContractRules::normalizeType($contract->contract_type ?? null) !== ContractRules::TYPE_PKWT) {
            throw new \DomainException('“End of PKWT” needs a running PKWT contract.');
        }

        return DB::transaction(function () use ($d, $employeeId, $kind, $type, $reason, $contract, $actorId) {
            $id = (int) DB::table('offboardings')->insertGetId([
                'employee_id' => $employeeId, 'contract_id' => $contract->contract_id ?? null, 'employee_type' => $kind, 'offboarding_type' => $type,
                'reason_code' => $reason, 'effective_date' => $d['effective_date'], 'last_working_day' => ($d['last_working_day'] ?? '') !== '' ? $d['last_working_day'] : null,
                'status' => 'in_progress', 'notes' => ($d['notes'] ?? '') !== '' ? (string) $d['notes'] : null, 'created_by' => $actorId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($this->templates()->where('is_active', 1) as $t) {
                if ($t->applies_to !== 'both' && strtolower($t->applies_to) !== strtolower($kind)) {
                    continue;
                }
                DB::table('offboarding_tasks')->insert([
                    'offboarding_id' => $id, 'task_key' => $t->task_key, 'name' => $t->name, 'description' => $t->description, 'status' => 'pending',
                    'due_date' => date('Y-m-d', strtotime($d['effective_date'] . ' ' . sprintf('%+d', $t->due_offset_days) . ' days')),
                    'sort_order' => $t->sort_order, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $this->history($employeeId, 'Offboarding started', (R::TYPES[$type] ?? $type) . ', effective ' . $d['effective_date'], $actorId);

            return $id;
        });
    }

    /** Kontrak yang sedang berjalan (aktif, bukan Draft/Terminated). */
    public function runningContract(int $employeeId): ?object
    {
        return DB::table('employee_contract')->where('employee_id', $employeeId)->where('is_active', 1)
            ->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhereNotIn('lifecycle_status', ['draft', 'terminated']))
            ->orderByDesc('start_date')->orderByDesc('contract_id')->first();
    }

    // ── Baca ──────────────────────────────────────────────────────────────

    public function find(int $id): ?object
    {
        $o = DB::table('offboardings as o')->join('employee as e', 'e.employee_id', '=', 'o.employee_id')->join('employee_basic_data as b', 'b.employee_id', '=', 'o.employee_id')
            ->where('o.id', $id)->first(['o.*', 'e.eci', 'e.is_active', 'b.first_name', 'b.last_name', 'b.nick_name', 'b.position', 'b.department', 'b.since_date']);
        if (!$o) {
            return null;
        }
        $o->name = trim(($o->first_name ?? '') . ' ' . ($o->last_name ?? '')) ?: ($o->nick_name ?: $o->eci);
        $o->type_label = R::TYPES[$o->offboarding_type] ?? $o->offboarding_type;
        $o->tasks = DB::table('offboarding_tasks')->where('offboarding_id', $id)->orderBy('sort_order')->orderBy('id')->get();
        $o->done = $o->tasks->where('status', 'done')->count();
        $o->total = $o->tasks->count();
        $o->percent = $o->total ? (int) round(100 * $o->done / $o->total) : 0;
        $o->overdue = $o->status === 'in_progress' ? $o->tasks->filter(fn ($t) => $t->status === 'pending' && $t->due_date && $t->due_date < now()->toDateString())->count() : 0;
        $o->settlement = DB::table('offboarding_settlements')->where('offboarding_id', $id)->first();
        if ($o->settlement) {
            $o->settlement->result_data = $o->settlement->result ? json_decode($o->settlement->result, true) : null;
        }
        $o->contract = $o->contract_id ? DB::table('employee_contract')->where('contract_id', $o->contract_id)->first() : null;
        $o->assets = $this->assetsOf((int) $o->employee_id);

        return $o;
    }

    /** Aset yang masih tercatat dipegang orang ini (Inventory & Assets). */
    public function assetsOf(int $employeeId)
    {
        return Schema::hasTable('inventory_assets')
            ? DB::table('inventory_assets')->where('assignee_employee_id', $employeeId)->get(['id', 'code', 'name', 'category', 'status', 'condition'])
            : collect();
    }

    /** @param  array{search?:string,status?:string,type?:string}  $f */
    public function listing(array $f, int $perPage): LengthAwarePaginator
    {
        $page = DB::table('offboardings as o')->join('employee as e', 'e.employee_id', '=', 'o.employee_id')->join('employee_basic_data as b', 'b.employee_id', '=', 'o.employee_id')
            ->when(($f['search'] ?? '') !== '', function ($q) use ($f) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $f['search']) . '%';
                $q->where(fn ($s) => $s->whereRaw("CONCAT_WS(' ', b.first_name, b.last_name) like ?", [$like])->orWhere('e.eci', 'like', $like)->orWhere('b.position', 'like', $like));
            })
            ->when(in_array($f['status'] ?? '', ['in_progress', 'completed', 'cancelled'], true), fn ($q) => $q->where('o.status', $f['status']))
            ->when(isset(R::TYPES[$f['type'] ?? '']), fn ($q) => $q->where('o.offboarding_type', $f['type']))
            ->select(['o.id', 'o.employee_id', 'o.employee_type', 'o.offboarding_type', 'o.effective_date', 'o.status', 'o.access_deactivated_at', 'e.eci', 'b.first_name', 'b.last_name', 'b.position'])
            ->selectRaw("(select count(*) from offboarding_tasks t where t.offboarding_id = o.id) as total, (select count(*) from offboarding_tasks t where t.offboarding_id = o.id and t.status = 'done') as done,
                (select count(*) from offboarding_tasks t where t.offboarding_id = o.id and t.status = 'pending' and t.due_date < CURDATE() and o.status = 'in_progress') as overdue")
            ->orderByRaw("o.status = 'in_progress' desc")->orderBy('o.effective_date')->paginate($perPage)->withQueryString();

        $page->getCollection()->transform(function ($r) {
            $r->name = trim(($r->first_name ?? '') . ' ' . ($r->last_name ?? '')) ?: $r->eci;
            $r->type_label = R::TYPES[$r->offboarding_type] ?? $r->offboarding_type;
            $r->percent = $r->total ? (int) round(100 * $r->done / $r->total) : 0;

            return $r;
        });

        return $page;
    }

    public function summary(): array
    {
        $r = DB::table('offboardings as o')->selectRaw("
            SUM(o.status = 'in_progress') as in_progress,
            SUM(o.status = 'in_progress' AND o.effective_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)) as due_week,
            SUM(o.status = 'in_progress' AND EXISTS (select 1 from offboarding_tasks t where t.offboarding_id = o.id and t.status = 'pending' and t.due_date < CURDATE())) as overdue,
            SUM(o.status = 'completed') as completed")->first();

        return array_map('intval', (array) $r);
    }

    // ── Tugas ─────────────────────────────────────────────────────────────

    /**
     * @param  array<int,array{status?:string,note?:string}>  $input  id tugas → isian
     * @throws \DomainException
     */
    public function saveTasks(int $id, array $input, int $actorId): void
    {
        $o = DB::table('offboardings')->where('id', $id)->first();
        if (!$o || $o->status !== 'in_progress') {
            throw new \DomainException('Only an offboarding in progress can be changed.');
        }
        DB::transaction(function () use ($id, $input, $actorId, $o) {
            foreach (DB::table('offboarding_tasks')->where('offboarding_id', $id)->get() as $t) {
                if (!isset($input[$t->id]) || !is_array($input[$t->id])) {
                    continue;
                }
                $status = ($input[$t->id]['status'] ?? $t->status) === 'done' ? 'done' : 'pending';
                // Tugas akses dikerjakan SISTEM (Deactivate access); jangan ditandai manual sebelum akses benar-benar dinonaktifkan.
                if ($t->task_key === self::TASK_ACCESS && $status === 'done' && $o->access_deactivated_at === null && $t->status !== 'done') {
                    throw new \DomainException('“Deactivate system access” is completed by the Deactivate access action on or after the effective date.');
                }
                $note = isset($input[$t->id]['note']) && is_scalar($input[$t->id]['note']) ? mb_substr(trim((string) $input[$t->id]['note']), 0, 500) : $t->note;
                $update = ['status' => $status, 'note' => $note === '' ? null : $note, 'updated_at' => now()];
                if ($status === 'done' && $t->status !== 'done') {
                    $update += ['completed_at' => now(), 'completed_by' => $actorId];
                } elseif ($status === 'pending') {
                    $update += ['completed_at' => null, 'completed_by' => null];
                }
                DB::table('offboarding_tasks')->where('id', $t->id)->update($update);
            }
        });
    }

    // ── Final Settlement ──────────────────────────────────────────────────

    /**
     * Hitung dan simpan. Hanya Internal; kalkulator harus menyala dan semua aturan hukum yang dibutuhkan sudah Reviewed.
     *
     * @param  array{wage_base_override?:?string,leave_days?:?string,retirement_benefit?:?string,separation_pay?:?string,other_compensation?:?string,legal_notes?:?string,internal_notes?:?string,reason_code?:?string,last_working_day?:?string}  $d
     * @throws \DomainException
     */
    public function calculate(int $id, array $d, int $actorId): array
    {
        $o = $this->find($id);
        if (!$o || $o->status !== 'in_progress') {
            throw new \DomainException('Only an offboarding in progress can be calculated.');
        }
        if ($o->employee_type === 'External') {
            throw new \DomainException('An external consultant has no final settlement (no severance or PKWT compensation).');
        }
        if (!$this->settings()->calculator_enabled) {
            throw new \DomainException('The settlement calculator is switched off in Offboarding Settings.');
        }
        if ($missing = $this->legal->blockers('offboarding')) {
            throw new \DomainException('These legal-basis entries are not reviewed yet: ' . implode(', ', $missing) . '.');
        }

        $reasonKey = R::reasonFor($o->offboarding_type, $d['reason_code'] ?? $o->reason_code);
        $reasons = $this->legal->payload('offboarding.reason_multipliers', $o->effective_date)['reasons'] ?? [];
        $reason = $reasons[$reasonKey ?? ''] ?? null;
        if ($reason === null) {
            // Jenis tanpa pengali pesangon di register (mis. end_of_engagement): hanya komponen manual.
            $reason = ['severance' => 0, 'service_award' => 0, 'leave' => true];
        }
        $severance = $this->legal->current('offboarding.severance_table', $o->effective_date);
        $tax = $this->legal->current('offboarding.severance_tax', $o->effective_date);
        $pkwt = $this->legal->current('offboarding.pkwt_compensation', $o->effective_date);
        $divisor = $this->legal->current('offboarding.leave_divisor', $o->effective_date);
        $rules = [
            'reason' => $reason, 'severance' => $severance->payload_data['severance'], 'service_award' => $severance->payload_data['service_award'],
            'tax' => $tax->payload_data['brackets'], 'pkwt_months_per_year' => (int) ($pkwt->payload_data['months_per_year'] ?? 12), 'type' => $o->offboarding_type,
        ];

        $wage = ($d['wage_base_override'] ?? '') !== '' ? ContractRules::parseAmount($d['wage_base_override']) : $this->wageBase($o->contract);
        // Masa kerja dihitung sejak tanggal bergabung; bila kosong, sejak kontrak pertama; bila itu pun kosong, 0.
        $start = substr((string) ($o->since_date ?: (DB::table('employee_contract')->where('employee_id', $o->employee_id)->min('start_date') ?: $o->effective_date)), 0, 10);
        $in = [
            'wage' => $wage, 'service_start' => $start, 'effective' => $o->effective_date,
            'contract_type' => $o->contract ? ContractRules::normalizeType($o->contract->contract_type) : null, 'contract_end' => $o->contract?->end_date,
            'leave_days' => ContractRules::parseAmount($d['leave_days'] ?? 0), 'leave_divisor' => (float) $this->settings()->leave_divisor,
            'retirement_benefit' => ContractRules::parseAmount($d['retirement_benefit'] ?? 0), 'separation_pay' => ContractRules::parseAmount($d['separation_pay'] ?? 0),
            'other_compensation' => ContractRules::parseAmount($d['other_compensation'] ?? 0),
        ];
        foreach (['leave_days', 'retirement_benefit', 'separation_pay', 'other_compensation'] as $k) {
            if ($in[$k] < 0) {
                throw new \DomainException('Amounts and days cannot be negative.');
            }
        }

        $result = R::calculate($in, $rules);
        $ids = array_filter(array_map(fn ($k) => $this->legal->current($k, $o->effective_date)?->id, [
            'offboarding.severance_table', 'offboarding.reason_multipliers', 'offboarding.severance_tax', 'offboarding.pkwt_compensation', 'offboarding.leave_divisor',
        ]));

        DB::transaction(function () use ($id, $d, $in, $result, $ids, $actorId, $o, $reasonKey) {
            DB::table('offboarding_settlements')->updateOrInsert(['offboarding_id' => $id], [
                'contract_type' => $in['contract_type'], 'wage_base_override' => ($d['wage_base_override'] ?? '') !== '' ? $in['wage'] : null,
                'leave_days' => $in['leave_days'], 'retirement_benefit' => $in['retirement_benefit'], 'separation_pay' => $in['separation_pay'], 'other_compensation' => $in['other_compensation'],
                'legal_notes' => ($d['legal_notes'] ?? '') !== '' ? (string) $d['legal_notes'] : null, 'internal_notes' => ($d['internal_notes'] ?? '') !== '' ? (string) $d['internal_notes'] : null,
                'result' => json_encode($result), 'legal_basis_ids' => json_encode(array_values($ids)), 'calculated_by' => $actorId, 'calculated_at' => now(), 'updated_at' => now(), 'created_at' => now(),
            ]);
            $update = ['updated_at' => now()];
            if ($reasonKey !== null) {
                $update['reason_code'] = $reasonKey;
            }
            if (($d['last_working_day'] ?? '') !== '') {
                $update['last_working_day'] = $d['last_working_day'];
            }
            DB::table('offboardings')->where('id', $id)->update($update);
            $this->history((int) $o->employee_id, 'Final settlement calculated', 'Offboarding #' . $id, $actorId);
        });

        return $result;
    }

    /** Dasar upah dari kontrak: gaji pokok + seluruh tunjangan di kontrak. */
    public function wageBase(?object $contract): float
    {
        if (!$contract) {
            return 0.0;
        }
        $components = ContractRules::cleanComponents(is_string($contract->salary_components) ? json_decode($contract->salary_components, true) : $contract->salary_components);

        return ContractRules::totalSalary($contract->salary !== null ? (float) $contract->salary : null, $components);
    }

    // ── Akses & penutupan ─────────────────────────────────────────────────

    /**
     * Nonaktifkan akses: hanya pada/ sesudah tanggal efektif. Mengikuti "wajib semua tugas lain selesai" bila saklarnya menyala.
     * Pengecualian (saklar "Allow force deactivate" menyala): dengan alasan tertulis (>= 5 karakter) HR boleh memaksa SEBELUM tanggal
     * efektif dan tanpa menunggu tugas lain; alasan dicatat di tugas dan riwayat karyawan. Penonaktifan otomatis tidak pernah memaksa.
     *
     * @throws \DomainException
     */
    public function deactivateAccess(int $id, int $actorId, bool $automatic = false, ?string $forceReason = null): void
    {
        $o = DB::table('offboardings')->where('id', $id)->first();
        if (!$o || $o->status !== 'in_progress') {
            throw new \DomainException('Only an offboarding in progress can deactivate access.');
        }
        if ($o->access_deactivated_at !== null) {
            throw new \DomainException('Access is already deactivated.');
        }
        $forceReason = trim((string) $forceReason);
        $force = false;
        if (!$automatic && $forceReason !== '') {
            if (empty($this->settings()->allow_force_deactivate)) {
                throw new \DomainException('Force deactivate is switched off. Turn it on in Offboarding Settings first.');
            }
            if (mb_strlen($forceReason) < 5) {
                throw new \DomainException('Give a reason of at least 5 characters to force deactivate.');
            }
            $force = true;
        }
        if (!$force && $o->effective_date > now()->toDateString()) {
            throw new \DomainException('Access can only be deactivated on or after the effective date (' . date('d M Y', strtotime($o->effective_date)) . ').');
        }
        if (!$force && $this->settings()->require_tasks_before_deactivate) {
            $open = DB::table('offboarding_tasks')->where('offboarding_id', $id)->where('status', 'pending')->where('task_key', '!=', self::TASK_ACCESS)->count();
            if ($open > 0) {
                throw new \DomainException("{$open} other task(s) are still pending. Finish them first (or switch the rule off in Offboarding Settings).");
            }
        }

        DB::transaction(function () use ($o, $id, $actorId, $automatic, $force, $forceReason) {
            DB::table('employee')->where('employee_id', $o->employee_id)->update(['is_active' => 0, 'updated_at' => now()]);
            DB::table('auth_users')->where('employee_id', $o->employee_id)->update(['is_active' => 0, 'remember_token' => null, 'cp_token' => null, 'updated_at' => now()]);
            if (Schema::hasTable('push_subscriptions')) {
                DB::table('push_subscriptions')->where('employee_id', $o->employee_id)->delete();
            }
            DB::table('offboardings')->where('id', $id)->update(['access_deactivated_at' => now(), 'updated_at' => now()]);
            DB::table('offboarding_tasks')->where('offboarding_id', $id)->where('task_key', self::TASK_ACCESS)->update([
                'status' => 'done', 'completed_at' => now(), 'completed_by' => $automatic ? null : $actorId, 'note' => $automatic ? 'Deactivated automatically on the effective date.' : ($force ? mb_substr('FORCED: ' . $forceReason, 0, 500) : null), 'updated_at' => now(),
            ]);
            $this->history((int) $o->employee_id, $force ? 'Access deactivated (forced)' : 'Access deactivated', 'Offboarding #' . $id . ($automatic ? ' (automatic)' : '') . ($force ? ' — forced before the effective date/checklist: ' . $forceReason : ''), $actorId);
        });
    }

    /**
     * Selesaikan: semua tugas selesai dan akses sudah dinonaktifkan. Menutup kontrak yang berjalan dan, untuk External, mengakhiri engagement.
     *
     * @throws \DomainException
     */
    public function complete(int $id, int $actorId): void
    {
        $o = $this->find($id);
        if (!$o || $o->status !== 'in_progress') {
            throw new \DomainException('Only an offboarding in progress can be completed.');
        }
        if ($o->done < $o->total) {
            throw new \DomainException(($o->total - $o->done) . ' task(s) are still pending.');
        }
        if ($o->access_deactivated_at === null) {
            throw new \DomainException('Deactivate access first (on or after the effective date).');
        }

        DB::transaction(function () use ($o, $actorId) {
            foreach (DB::table('employee_contract')->where('employee_id', $o->employee_id)->where('is_active', 1)->pluck('contract_id') as $contractId) {
                $c = EmployeeContract::find($contractId);
                if (!$c) {
                    continue;
                }
                $end = $c->end_date?->toDateString();
                $c->update([
                    'lifecycle_status' => ContractRules::STATUS_TERMINATED, 'is_active' => 0,
                    'end_date' => ($end === null || $end > $o->effective_date) ? $o->effective_date : $end,
                ]);
            }
            if ($o->employee_type === 'External') {
                DB::table('employee_engagement')->where('employee_id', $o->employee_id)
                    ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>', $o->effective_date))->update(['end_date' => $o->effective_date, 'updated_at' => now()]);
            }
            DB::table('offboardings')->where('id', $o->id)->update(['status' => 'completed', 'completed_at' => now(), 'completed_by' => $actorId, 'updated_at' => now()]);
            $this->history((int) $o->employee_id, 'Offboarding completed', 'Offboarding #' . $o->id, $actorId);
        });
    }

    /** @throws \DomainException */
    public function cancel(int $id, int $actorId, string $reason): void
    {
        $o = DB::table('offboardings')->where('id', $id)->first();
        if (!$o || $o->status !== 'in_progress') {
            throw new \DomainException('Only an offboarding in progress can be cancelled.');
        }
        if ($o->access_deactivated_at !== null) {
            throw new \DomainException('Access was already deactivated, so this offboarding cannot be cancelled. Reactivate the person in Employee Data first.');
        }
        if (trim($reason) === '') {
            throw new \DomainException('Give a reason for cancelling.');
        }
        DB::table('offboardings')->where('id', $id)->update(['status' => 'cancelled', 'cancelled_at' => now(), 'cancelled_by' => $actorId, 'cancel_reason' => mb_substr(trim($reason), 0, 500), 'updated_at' => now()]);
        $this->history((int) $o->employee_id, 'Offboarding cancelled', trim($reason), $actorId);
    }

    // ── Perintah harian ───────────────────────────────────────────────────

    /**
     * Dipanggil `offboarding:process`: mengingatkan tugas yang segera jatuh tempo dan (bila saklar menyala) menonaktifkan akses pada tanggal efektif.
     * Saklar modul mati → tidak berbuat apa-apa.
     *
     * @return array{reminded:int,deactivated:int,skipped:string[]}
     */
    public function processDaily(bool $dryRun = false): array
    {
        $out = ['reminded' => 0, 'deactivated' => 0, 'skipped' => []];
        $s = $this->settings();
        if (!$s->module_enabled) {
            $out['skipped'][] = 'Offboarding is switched off.';

            return $out;
        }

        $today = now()->toDateString();
        $until = now()->addDays((int) $s->reminder_days)->toDateString();
        $tasks = DB::table('offboarding_tasks as t')->join('offboardings as o', 'o.id', '=', 't.offboarding_id')
            ->where('o.status', 'in_progress')->where('t.status', 'pending')->whereNotNull('t.due_date')->where('t.due_date', '<=', $until)
            ->where(fn ($q) => $q->whereNull('t.reminded_at')->orWhere('t.reminded_at', '<', $today))
            ->get(['t.id', 't.name', 't.due_date', 'o.id as offboarding_id', 'o.employee_id']);
        foreach ($tasks as $t) {
            $out['reminded']++;
            if ($dryRun) {
                continue;
            }
            $name = DB::table('employee_basic_data')->where('employee_id', $t->employee_id)->selectRaw("CONCAT_WS(' ', first_name, last_name) n")->value('n');
            $this->notifyHr('offboarding_task_due', trim((string) $name) . ': “' . $t->name . '” is due ' . ($t->due_date < $today ? 'since ' : 'on ') . date('d M Y', strtotime($t->due_date)) . '.', "/general/offboarding/{$t->offboarding_id}");
            DB::table('offboarding_tasks')->where('id', $t->id)->update(['reminded_at' => now()]);
        }

        if (!$s->auto_deactivate) {
            $out['skipped'][] = 'Automatic deactivation is switched off.';

            return $out;
        }
        foreach (DB::table('offboardings')->where('status', 'in_progress')->whereNull('access_deactivated_at')->where('effective_date', '<=', $today)->get() as $o) {
            try {
                if (!$dryRun) {
                    $this->deactivateAccess((int) $o->id, 0, true);
                }
                $out['deactivated']++;
            } catch (\DomainException $e) {
                $out['skipped'][] = "Offboarding #{$o->id}: " . $e->getMessage();
            }
        }

        return $out;
    }

    // ── internal ──────────────────────────────────────────────────────────

    private function history(int $employeeId, string $action, string $description, int $actorId): void
    {
        try {
            DB::table('employee_history')->insert([
                'employee_id' => $employeeId, 'action' => $action, 'description' => mb_substr($description, 0, 500),
                'performed_by' => $actorId ?: null, 'performed_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('Offboarding: history not written', ['error' => $e->getMessage()]);
        }
    }

    /** Pemegang menu daftar Offboarding (kotak View) mendapat bel. */
    private function notifyHr(string $type, string $message, string $link): void
    {
        $ids = DB::table('employee_role_assignment as a')->join('role_menu as rm', 'rm.role_id', '=', 'a.role_id')->join('menu as m', 'm.id', '=', 'rm.menu_id')
            ->join('employee as e', 'e.employee_id', '=', 'a.employee_id')
            ->where('m.slug', 'general.offboarding.list')->where('rm.can_view', true)->where('e.is_active', 1)->distinct()->pluck('a.employee_id');
        foreach ($ids as $id) {
            try {
                Notification::create(['employee_id' => (int) $id, 'type' => $type, 'preview' => mb_strimwidth($message, 0, 250, '…'), 'link' => $link]);
            } catch (\Throwable $e) {
                Log::error('Offboarding: notification failed', ['to' => $id, 'error' => $e->getMessage()]);
            }
        }
    }
}
