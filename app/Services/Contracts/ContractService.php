<?php

namespace App\Services\Contracts;

use App\Models\ContractOption;
use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\Letterhead;
use App\Services\Letters\LetterService;
use App\Support\Contracts\ContractHtmlSanitizer;
use App\Support\Contracts\ContractPlaceholders;
use App\Support\Contracts\ContractRules;
use App\Support\Contracts\PageSettings;
use App\Support\Contracts\SigningRules;
use App\Support\External\ExternalRules;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Menu Contract (HR & General → Contract): daftar, riwayat per karyawan, kesiapan data, simpan (nomor, status,
 * template beku), dan perakitan dokumen. Aturan murni ada di ContractRules; kelas ini yang berbicara dengan database.
 *
 * Kegagalan yang bisa dibaca HR dilempar sebagai \DomainException (pesan bahasa Inggris, tampil apa adanya).
 */
class ContractService
{
    /** Pesan hasil sinkron gaji kontrak → Salary Components pada simpan terakhir (null = tidak ada). */
    public ?string $salarySyncNote = null;

    /** Jenis surat pada Letter Templates → Settings tempat kop surat kontrak dipasang. */
    public const LETTER_TYPE = Letterhead::TYPE_EMPLOYMENT_CONTRACT;

    // ── Daftar ────────────────────────────────────────────────────────────

    /**
     * Satu baris per karyawan aktif: kontrak TERBARU-nya (id tertinggi), atau tanpa kontrak.
     *
     * @param  array{search?:string,type?:string,status?:string}  $filters
     */
    public function listing(array $filters, int $perPage): LengthAwarePaginator
    {
        $today = now()->toDateString();

        $page = $this->baseQuery()
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']) . '%';
                $q->where(fn ($s) => $s
                    ->whereRaw("CONCAT_WS(' ', b.first_name, b.last_name) like ?", [$like])
                    ->orWhere('e.eci', 'like', $like)
                    ->orWhere('c.contract_number', 'like', $like)
                    ->orWhere('b.position', 'like', $like)
                    ->orWhere('c.position', 'like', $like)
                    ->orWhere('b.department', 'like', $like)
                    ->orWhereExists(fn ($x) => $x->selectRaw('1')->from('employee_identification as i')
                        ->whereColumn('i.employee_id', 'e.employee_id')
                        ->where('i.identification_type', 'KTP')
                        ->where('i.identification_number', 'like', $like)));
            })
            // Column filters (header funnels of Management → Employee → Contract)
            ->when(($filters['employee'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['employee']) . '%';
                $q->where(fn ($s) => $s
                    ->whereRaw("CONCAT_WS(' ', b.first_name, b.last_name) like ?", [$like])
                    ->orWhere('b.nick_name', 'like', $like)
                    ->orWhere('e.eci', 'like', $like));
            })
            ->when(($filters['number'] ?? '') !== '', fn ($q) => $q->where('c.contract_number', 'like',
                '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['number']) . '%'))
            ->when(($filters['department'] ?? '') !== '', fn ($q) => $q->where('b.department', $filters['department']))
            ->when(($filters['end_from'] ?? '') !== '', fn ($q) => $q->where('c.end_date', '>=', $filters['end_from']))
            ->when(($filters['end_to'] ?? '') !== '', fn ($q) => $q->where('c.end_date', '<=', $filters['end_to']))
            ->when(isset(ContractRules::TYPES[$filters['type'] ?? '']), fn ($q) => $q
                ->whereIn(DB::raw('UPPER(c.contract_type)'), $this->typeAliases($filters['type'])))
            // A custom contract type chosen in Settings is filed under its own code.
            ->when(($filters['kind'] ?? '') !== '', fn ($q) => $q->where('c.contract_kind', $filters['kind']))
            ->tap(fn ($q) => $this->applyStatusFilter($q, $filters['status'] ?? '', $today))
            ->orderBy('b.first_name')->orderBy('e.employee_id')
            ->paginate($perPage)->withQueryString();

        $page->getCollection()->transform(fn ($row) => $this->decorate($row, $today));

        return $page;
    }

    /** Departments of the active employees, for the Department filter. @return array<string,string> */
    public function departments(): array
    {
        return $this->baseQuery(false)->whereNotNull('b.department')->where('b.department', '<>', '')
            ->distinct()->orderBy('b.department')->pluck('b.department', 'b.department')->all();
    }

    /**
     * Figures of the Contract dashboard — one pass over every active employee's latest contract, so the numbers
     * agree with the list and its status filter.
     *
     * @param  int  $window  days ahead that count as "ending soon" (30 / 60 / 90)
     */
    public function dashboard(int $window = 30): array
    {
        $window = in_array($window, [30, 60, 90], true) ? $window : (int) \App\Services\Management\EmployeeMasterData::rule('contract', 'ending_soon_days');
        $today = now()->toDateString();
        $until = fn (int $days) => now()->addDays($days)->toDateString();

        $rows = $this->baseQuery()->orderBy('b.first_name')->get()->map(fn ($r) => $this->decorate($r, $today));
        $running = $rows->where('effective_status', ContractRules::STATUS_ACTIVE);
        $endingIn = fn (int $days) => $running->filter(fn ($r) => $r->end_date && $r->end_date >= $today && $r->end_date <= $until($days));

        $ending = $endingIn($window)->sortBy('end_date')->values()->each(function ($r) {
            $r->days_left = (int) now()->startOfDay()->diffInDays(Carbon::parse($r->end_date)->startOfDay());
        });

        $expired = $rows->where('effective_status', ContractRules::STATUS_EXPIRED)->sortByDesc('end_date')->values();
        $none = $rows->where('effective_status', 'none')->values();

        $count = fn ($collection, string $by, string $fallback) => $collection
            ->groupBy(fn ($r) => ($r->{$by} ?? '') !== '' ? $r->{$by} : $fallback)->map->count()->sortDesc();

        $months = collect(range(0, 5))->map(function ($ahead) use ($running) {
            $start = now()->startOfMonth()->addMonths($ahead);

            return [
                'label' => $start->format('M Y'),
                'count' => $running->filter(fn ($r) => $r->end_date && substr($r->end_date, 0, 7) === $start->format('Y-m'))->count(),
            ];
        });

        return [
            'window'  => $window,
            'cards'   => [
                'employees' => $rows->count(),
                'active'    => $running->count(),
                'permanent' => $running->filter(fn ($r) => !$r->end_date)->count(),
                'ending30'  => $endingIn(30)->count(),
                'ending60'  => $endingIn(60)->count(),
                'ending90'  => $endingIn(90)->count(),
                'expired'   => $expired->count(),
                'none'      => $none->count(),
                'draft'     => $rows->where('effective_status', ContractRules::STATUS_DRAFT)->count(),
            ],
            'ending'  => $ending,
            'recent'  => $rows->where('has_contract', true)->sortByDesc('contract_id')->take(10)->values(),
            'records' => $rows->where('has_contract', true)->count(),
            'expired' => $expired->take(8),
            'none'    => $none->take(8),
            'byType'  => $count($rows->where('has_contract', true), 'type', 'Other'),
            'byDept'  => $count($running, 'employee_department', 'No department')->take(8),
            'months'  => $months,
        ];
    }

    /** Hitungan untuk kartu ringkasan di atas daftar. */
    public function summary(): array
    {
        $today = now()->toDateString();
        $soon = now()->addDays((int) \App\Services\Management\EmployeeMasterData::rule('contract', 'ending_soon_days'))->toDateString();

        $row = $this->baseQuery(false)->selectRaw(
            "COUNT(*) as employees,
             SUM(c.contract_id IS NULL) as none,
             SUM(c.lifecycle_status = 'draft') as draft,
             SUM(IFNULL(c.lifecycle_status,'') NOT IN ('draft','terminated') AND c.is_active = 1 AND (c.end_date IS NULL OR c.end_date >= ?)) as active,
             SUM(IFNULL(c.lifecycle_status,'') NOT IN ('draft','terminated') AND c.is_active = 1 AND c.end_date IS NOT NULL AND c.end_date >= ? AND c.end_date <= ?) as ending",
            [$today, $today, $soon]
        )->first();

        return [
            'employees' => (int) $row->employees, 'none' => (int) $row->none, 'draft' => (int) $row->draft,
            'active' => (int) $row->active, 'ending' => (int) $row->ending,
        ];
    }

    private function baseQuery(bool $columns = true)
    {
        $latest = DB::table('employee_contract')->select('employee_id', DB::raw('MAX(contract_id) as cid'))->groupBy('employee_id');

        return DB::table('employee as e')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->leftJoinSub($latest, 'lc', 'lc.employee_id', '=', 'e.employee_id')
            ->leftJoin('employee_contract as c', 'c.contract_id', '=', 'lc.cid')
            ->where('e.is_active', 1)
            ->where(fn ($q) => $q->where('b.deletion_flag', 0)->orWhereNull('b.deletion_flag'))
            // Internal and external people have separate contract menus: consultants live in External → External Contract.
            ->where(fn ($q) => $q->whereNull('b.employee_type')->orWhere('b.employee_type', '<>', 'External'))
            ->when($columns, fn ($q) => $q->select([
                'e.employee_id', 'e.eci', 'b.first_name', 'b.last_name', 'b.nick_name',
                'b.position as employee_position', 'b.department as employee_department', 'b.employee_type',
                'c.contract_id', 'c.contract_number', 'c.contract_type', 'c.start_date', 'c.end_date',
                'c.is_active', 'c.lifecycle_status', 'c.position as contract_position', 'c.position_type', 'c.contract_kind', 'c.signed_file_path', 'c.sign_stage',
            ]));
    }

    /** Cermin SQL dari ContractRules::effectiveStatus(). */
    public function applyStatusFilter($q, string $status, string $today): void
    {
        $notClosed = "IFNULL(c.lifecycle_status,'') NOT IN ('draft','terminated')";

        match ($status) {
            'none'                          => $q->whereNull('c.contract_id'),
            ContractRules::STATUS_DRAFT      => $q->where('c.lifecycle_status', 'draft'),
            ContractRules::STATUS_TERMINATED => $q->where('c.lifecycle_status', 'terminated'),
            ContractRules::STATUS_ACTIVE     => $q->whereRaw("$notClosed AND c.is_active = 1 AND (c.end_date IS NULL OR c.end_date >= ?)", [$today]),
            ContractRules::STATUS_EXPIRED    => $q->whereRaw("$notClosed AND (c.lifecycle_status = 'expired' OR (c.is_active = 1 AND c.end_date IS NOT NULL AND c.end_date < ?))", [$today]),
            ContractRules::STATUS_INACTIVE   => $q->whereRaw("c.contract_id IS NOT NULL AND $notClosed AND c.is_active = 0 AND IFNULL(c.lifecycle_status,'') <> 'expired'"),
            default                          => null,
        };
    }

    /** @return string[] */
    private function typeAliases(string $type): array
    {
        return match ($type) {
            ContractRules::TYPE_PKWT     => ['PKWT', 'SPKWT'],
            ContractRules::TYPE_PKWTT    => ['PKWTT', 'SPKWTT', 'PERMANENT'],
            ContractRules::TYPE_INTERNSHIP => ['INTERNSHIP', 'INTERN', 'MAGANG'],
            ContractRules::TYPE_ADDENDUM => ['ADDENDUM', 'ADD'],
            default                      => ['EXTERNAL', 'EXT', 'PKS'],
        };
    }

    private function decorate(object $row, string $today): object
    {
        $row->name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ($row->nick_name ?: $row->eci);
        $row->has_contract = $row->contract_id !== null;
        $row->has_signed = !empty($row->signed_file_path);
        $row->effective_status = $row->has_contract
            ? ContractRules::effectiveStatus($row->lifecycle_status, (bool) $row->is_active, $row->end_date, $today)
            : 'none';
        $row->type = ContractRules::normalizeType($row->contract_type);
        $row->position = $row->contract_position ?: $row->employee_position;
        $row->ending_soon = $row->effective_status === ContractRules::STATUS_ACTIVE && $row->end_date
            && $row->end_date <= now()->addDays((int) \App\Services\Management\EmployeeMasterData::rule('contract', 'ending_soon_days'))->toDateString();

        return $row;
    }

    // ── Satu karyawan ─────────────────────────────────────────────────────

    /** Kepala halaman karyawan, atau null bila tak aktif / tak ada. */
    public function employeeHeader(int $employeeId): ?object
    {
        $row = $this->baseQuery()->where('e.employee_id', $employeeId)->first();

        return $row ? $this->decorate($row, now()->toDateString()) : null;
    }

    /** Riwayat kontrak (terbaru dulu) dengan status berjalan dan total gaji. */
    public function history(int $employeeId): array
    {
        $today = now()->toDateString();

        return EmployeeContract::where('employee_id', $employeeId)
            ->orderByDesc('start_date')->orderByDesc('contract_id')->get()
            ->map(function (EmployeeContract $c) use ($today) {
                $components = ContractRules::cleanComponents($c->salary_components);
                $c->setAttribute('effective_status', ContractRules::effectiveStatus($c->lifecycle_status, (bool) $c->is_active, $c->end_date?->toDateString(), $today));
                $c->setAttribute('type_key', ContractRules::normalizeType($c->contract_type));
                $c->setAttribute('components', $components);
                $c->setAttribute('total_salary', ContractRules::totalSalary($c->salary !== null ? (float) $c->salary : null, $components));

                return $c;
            })->all();
    }

    /** Kartu "Contract Readiness". */
    public function readiness(int $employeeId): array
    {
        $items = config('hc_contract.readiness', []);

        // Butir bertanda `for => internal` tidak disyaratkan bagi konsultan External (kontrak External memakai layanan ini juga).
        $external = DB::table('employee_basic_data')->where('employee_id', $employeeId)->value('employee_type') === 'External';
        $items = array_values(array_filter($items, fn ($i) => !($external && ($i['for'] ?? null) === 'internal')));

        $address = DB::table('employee_address')->where('employee_id', $employeeId)
            ->whereIn('address_type', ['Home', 'primary'])->get();
        $identification = DB::table('employee_identification')->where('employee_id', $employeeId)->get();
        $bank = DB::table('employee_bank')->where('employee_id', $employeeId)->get();
        $attachments = DB::table('employee_attachment')->where('employee_id', $employeeId)->get(['document_type', 'document_title', 'file_name']);
        $hr = DB::table('employee_hr_profile')->where('employee_id', $employeeId)->first();

        $filled = fn ($v) => $v !== null && trim((string) $v) !== '';
        $ok = [];

        foreach ($items as $item) {
            [$kind, $arg] = explode(':', $item['check'], 2);

            $ok[$item['key']] = match ($kind) {
                'identification' => $identification->contains(fn ($r) => strtoupper((string) $r->identification_type) === $arg && $filled($r->identification_number)),
                'address'        => $address->contains(fn ($r) => $filled($r->{$arg} ?? null)),
                'bank'           => $bank->contains(fn ($r) => $filled($r->{$arg} ?? null)),
                'hr'             => $hr !== null && $filled($hr->{$arg} ?? null),
                // Dokumen dikenali dari jenis/judul/nama berkas yang memuat kata kuncinya (KTP, KK|Kartu Keluarga, CV|Curriculum Vitae, ...).
                'attachment'     => $attachments->contains(fn ($r) => (bool) preg_match(
                    '/\b(?:' . implode('|', array_map(fn ($w) => preg_quote(trim($w), '/'), explode('|', $arg))) . ')\b/i',
                    trim(($r->document_type ?? '') . ' ' . ($r->document_title ?? '') . ' ' . ($r->file_name ?? '')))),
                default          => false,
            };
        }

        return ContractRules::readiness($items, $ok);
    }

    // ── Simpan ────────────────────────────────────────────────────────────

    /**
     * Isian kontrak baru yang MENGIKUTI data karyawan (tidak diketik manual): tanggal mulai, departemen, jabatan, tipe jabatan.
     *   mulai  = sehari sesudah kontrak terakhir berakhir; bila belum ada kontrak = Since Date (tanggal bergabung)
     *   tipe jabatan = tipe jabatan aktif yang namanya terkandung dalam jabatan karyawan (mis. "Consultant"), else tipe bawaan Settings
     *
     * @return array{start:?string,since:?string,department:?string,position:?string,position_type:?string}
     */
    public function employeeDefaults(int $employeeId): array
    {
        $basic = DB::table('employee_basic_data')->where('employee_id', $employeeId)->first(['since_date', 'department', 'position']);
        $since = $basic && $basic->since_date ? substr((string) $basic->since_date, 0, 10) : null;

        $lastEnd = DB::table('employee_contract')->where('employee_id', $employeeId)->whereNotNull('end_date')->max('end_date');
        $start = $lastEnd ? Carbon::parse($lastEnd)->addDay()->toDateString() : $since;

        $position = $basic && trim((string) $basic->position) !== '' ? trim((string) $basic->position) : null;
        $options = app(ContractOptionService::class);
        $positionType = null;
        if ($position !== null) {
            foreach ($options->positionTypes(ContractOption::SCOPE_INTERNAL) as $name) {
                if ($name !== '' && stripos($position, $name) !== false) {
                    $positionType = $name;
                    break;
                }
            }
        }

        return [
            'start'         => $start,
            'since'         => $since,
            'department'    => $basic && trim((string) $basic->department) !== '' ? trim((string) $basic->department) : null,
            'position'      => $position,
            'position_type' => $positionType ?? $options->defaultPositionType(ContractOption::SCOPE_INTERNAL),
        ];
    }

    /**
     * Lengkapi isian kosong dari data karyawan (hanya untuk kontrak BARU / pratinjau). Nilai yang dikirim tetap dipakai,
     * jadi formulir yang masih mengirim departemen/jabatan tidak berubah perilakunya.
     *
     * @param  array<string,mixed>  $d
     * @return array<string,mixed>
     */
    public function fillFromEmployee(int $employeeId, array $d): array
    {
        $def = $this->employeeDefaults($employeeId);
        foreach (['department', 'position', 'position_type'] as $key) {
            if (($d[$key] ?? '') === '' && $def[$key] !== null) {
                $d[$key] = $def[$key];
            }
        }
        if (($d['start_date'] ?? '') === '' && $def['start'] !== null) {
            $d['start_date'] = $def['start'];
        }

        // Akhir kontrak mengikuti lama kontrak (bulan) bila tanggal akhir tidak diisi: mulai + N bulan − 1 hari.
        $months = (int) ($d['duration_months'] ?? 0);
        if (($d['end_date'] ?? '') === '' && $months > 0 && ($d['start_date'] ?? '') !== '') {
            $d['end_date'] = Carbon::parse($d['start_date'])->addMonthsNoOverflow($months)->subDay()->toDateString();
        }

        return $d;
    }

    /** activate | create | off — lihat config/hc_contract.php. */
    public static function gate(): string
    {
        $gate = (string) config('hc_contract.readiness_gate', 'activate');

        return in_array($gate, ['activate', 'create', 'off'], true) ? $gate : 'activate';
    }

    /** @throws \DomainException bila data karyawan belum lengkap */
    private function assertReady(int $employeeId, string $action): void
    {
        $readiness = $this->readiness($employeeId);
        if (!$readiness['ready']) {
            throw new \DomainException("Complete the employee data before you {$action}: " . implode(', ', array_column($readiness['missing'], 'label')) . '.');
        }
    }

    /**
     * Buat kontrak baru (draft atau aktif). Nomor dibuat sistem.
     *
     * @param  array<string,mixed>  $d  data yang sudah divalidasi controller
     * @throws \DomainException
     */
    public function create(int $employeeId, array $d, int $actorId, bool $canSalary): EmployeeContract
    {
        if (self::gate() === 'create') {
            $this->assertReady($employeeId, 'create a contract');
        }

        $d = $this->fillFromEmployee($employeeId, $d);
        if (($d['start_date'] ?? '') === '') {
            throw new \DomainException('The start date follows the employee’s Join Date, which is empty. Fill in Since Date in the employee’s Basic Data, or enter the start date.');
        }

        return DB::transaction(function () use ($employeeId, $d, $actorId, $canSalary) {
            $attrs = $this->attributes($employeeId, null, $d, $canSalary);
            $attrs['employee_id'] = $employeeId;
            $attrs['created_by'] = $actorId;
            $attrs['contract_number'] = $this->allocateNumber($attrs['contract_type'], now());

            $contract = EmployeeContract::create($attrs);
            $this->syncSalary($contract, $canSalary, $d);

            return $contract;
        });
    }

    /**
     * Ubah kontrak. Template dibekukan begitu kontrak menjadi Active; kontrak aktif yang dikembalikan ke Draft
     * melepas bekuannya agar template terbaru dipakai lagi.
     *
     * @throws \DomainException
     */
    public function update(EmployeeContract $contract, array $d, bool $canSalary): EmployeeContract
    {
        return DB::transaction(function () use ($contract, $d, $canSalary) {
            $attrs = $this->attributes((int) $contract->employee_id, $contract, $d, $canSalary);
            $contract->update($attrs);
            $contract->refresh();
            $this->syncSalary($contract, $canSalary, $d);

            return $contract;
        });
    }

    /**
     * Hanya Draft yang boleh dihapus; kontrak yang pernah berlaku diakhiri (Terminated), bukan dihapus.
     *
     * @throws \DomainException
     */
    public function delete(EmployeeContract $contract): void
    {
        if ($contract->lifecycle_status !== ContractRules::STATUS_DRAFT) {
            throw new \DomainException('Only a Draft contract can be deleted. End a running contract by setting its status to Terminated.');
        }
        $contract->delete();
    }

    /** Kontrak aktif yang disimpan pemegang "View Salary" (gaji ikut dikirim) → perbarui Salary Components. */
    private function syncSalary(EmployeeContract $contract, bool $canSalary, array $d): void
    {
        $this->salarySyncNote = null;
        if ($canSalary && array_key_exists('salary', $d)) {
            $this->salarySyncNote = app(\App\Services\Payroll\ContractSalarySync::class)->sync($contract);
        }
    }

    /** Nilai kolom dari kiriman formulir, dengan semua aturan bisnis. */
    private function attributes(int $employeeId, ?EmployeeContract $existing, array $d, bool $canSalary): array
    {
        $type = ContractRules::normalizeType($d['contract_type'] ?? '');
        if ($type === null) {
            throw new \DomainException('Choose a contract type.');
        }

        $status = $d['status'] ?? ContractRules::STATUS_DRAFT;
        if (!in_array($status, ContractRules::SETTABLE_STATUSES, true)) {
            throw new \DomainException('Choose a document status.');
        }

        $start = $d['start_date'] ?? null;
        $end = ($d['end_date'] ?? '') !== '' ? $d['end_date'] : null;
        if ($error = ContractRules::dateError($type, $start, $end, (int) config('hc_contract.pkwt_max_months', 60))) {
            throw new \DomainException($error);
        }

        // Satu kontrak berlaku per karyawan: yang lama harus diakhiri lebih dulu (bukan ditimpa diam-diam).
        if ($status === ContractRules::STATUS_ACTIVE) {
            // Pagar kesiapan data: hanya saat kontrak BARU menjadi Active (mengubah kontrak yang sudah berlaku tidak dihalangi).
            if (self::gate() === 'activate' && !($existing && $existing->is_active)) {
                $this->assertReady($employeeId, 'activate a contract');
            }
            $other = $this->runningContract($employeeId, $existing?->contract_id);
            if ($other) {
                throw new \DomainException("This employee already has a running contract ({$other->contract_number}). Set it to Terminated, or let it end, before activating another.");
            }
        }

        $template = $this->resolveTemplate($d['template_id'] ?? null, $type, $d['position'] ?? null, $d['position_type'] ?? null);
        if ($template === null) {
            throw new \DomainException('No active template exists for this contract type. Add one in the Templates tab first.');
        }

        $attrs = [
            'contract_type' => $type,
            'contract_kind' => $this->nullable($d['contract_kind'] ?? null),
            'contract_name' => $template->name,
            'lifecycle_status' => $status,
            'is_active'     => ContractRules::isActiveFor($status),
            'start_date'    => $start,
            'end_date'      => $end,
            'signed_date'   => ($d['signed_date'] ?? '') !== '' ? $d['signed_date'] : null,
            'contract_date' => ($d['signed_date'] ?? '') !== '' ? $d['signed_date'] : $start,
            'position'      => $this->nullable($d['position'] ?? null),
            'position_type' => $this->nullable($d['position_type'] ?? null),
            'department'    => $this->nullable($d['department'] ?? null),
            'work_location' => $this->nullable($d['work_location'] ?? null),
            'notes'         => $this->nullable($d['notes'] ?? null),
            'work_volume'   => $this->nullable($d['work_volume'] ?? null),
            'template_id'   => $template->id,
        ];

        // The text of the document. Three cases:
        //  · HR generated it and edited it by hand (body_html + body_customized sent): that text is kept as it is, with its paper
        //    settings, whatever the status — a template change must not overwrite what Legal / HR / RPMO adjusted.
        //  · an already hand-edited contract saved without the text (a status change, a date): the saved text stays.
        //  · otherwise: a Draft follows the template; Active freezes it (once, or again when its template is replaced).
        $sentBody = trim((string) ($d['body_html'] ?? ''));
        if ($sentBody !== '' && !empty($d['body_customized'])) {
            $clean = ContractHtmlSanitizer::clean($sentBody);
            if (trim(strip_tags($clean)) === '') {
                throw new \DomainException('The contract text cannot be empty.');
            }
            $attrs['body_html'] = $clean;
            $attrs['body_customized'] = true;
            $attrs['page_json'] = array_key_exists('page_size', $d) || array_key_exists('margin_top', $d)
                ? PageSettings::store($d['page_size'] ?? null, $d['page_orientation'] ?? null, [
                    'top' => $d['margin_top'] ?? null, 'right' => $d['margin_right'] ?? null, 'bottom' => $d['margin_bottom'] ?? null, 'left' => $d['margin_left'] ?? null,
                ])
                : ($existing?->page_json ?: self::templatePage($template));
        } elseif ($existing && $existing->body_customized && $existing->body_html && empty($d['reset_body'])) {
            $attrs['body_html'] = $existing->body_html;
            $attrs['body_customized'] = true;
            $attrs['page_json'] = $existing->page_json ?: self::templatePage($template);
        } else {
            $attrs['body_customized'] = false;
            $attrs['body_html'] = $status === ContractRules::STATUS_ACTIVE
                ? (($existing && !$existing->body_customized && $existing->body_html && (int) $existing->template_id === $template->id) ? $existing->body_html : $template->body_html)
                : ($status === ContractRules::STATUS_DRAFT ? null : ($existing && !$existing->body_customized ? $existing->body_html : null));
            // Paper size, orientation and margins are frozen together with the text.
            $attrs['page_json'] = $status === ContractRules::STATUS_ACTIVE
                ? (($existing && !$existing->body_customized && $existing->body_html && $existing->page_json && (int) $existing->template_id === $template->id) ? $existing->page_json : self::templatePage($template))
                : ($status === ContractRules::STATUS_DRAFT ? null : ($existing && !$existing->body_customized ? $existing->page_json : null));
        }

        // Nomor: dibuat sistem saat pembuatan; boleh dikoreksi HR selama tetap unik.
        if ($existing) {
            $number = trim((string) ($d['contract_number'] ?? $existing->contract_number));
            if ($number !== '' && $number !== $existing->contract_number) {
                if (EmployeeContract::where('contract_number', $number)->where('contract_id', '!=', $existing->contract_id)->exists()) {
                    throw new \DomainException("Contract number {$number} is already used.");
                }
                $attrs['contract_number'] = $number;
            }
        }

        // Gaji hanya diubah oleh pemegang "View Salary": yang lain tidak melihatnya, jadi tak boleh menimpanya dengan kosong.
        // Hanya bila kuncinya DIKIRIM: permintaan tanpa kolom gaji (mis. ganti status saja) tidak boleh mengosongkannya.
        if ($canSalary && array_key_exists('salary', $d)) {
            $attrs['salary'] = ($d['salary'] ?? '') !== '' ? ContractRules::parseAmount($d['salary']) : null;
            if ($attrs['salary'] !== null && $attrs['salary'] < 0) {
                throw new \DomainException('The basic salary cannot be negative.');
            }
            $attrs['salary_components'] = ContractRules::cleanComponents($d['components'] ?? []);
        }

        // Company signatory: the one picked on the contract, else the default of its template (if that person can still sign).
        $signerId = ($d['signatory_employee_id'] ?? '') !== '' ? (int) $d['signatory_employee_id'] : null;
        $person = null;
        if ($signerId !== null) {
            $person = LetterService::signatoryOptions()->firstWhere('id', $signerId);
            if (!$person) {
                throw new \DomainException('That person is not a signer (Letter Templates → Settings → Signers).');
            }
        } elseif ($template->signatory_employee_id) {
            $person = LetterService::signatoryOptions()->firstWhere('id', (int) $template->signatory_employee_id);
        }
        $attrs['signatory_employee_id'] = $person['id'] ?? null;
        $attrs['signatory_name'] = $person['name'] ?? null;
        $attrs['signatory_title'] = $person['position'] ?? null;

        return $attrs;
    }

    private function nullable($v): ?string
    {
        $v = trim((string) $v);

        return $v === '' ? null : $v;
    }

    /** Kontrak lain milik karyawan yang SEDANG berlaku (status berjalan Active). */
    private function runningContract(int $employeeId, ?int $exceptId): ?EmployeeContract
    {
        $today = now()->toDateString();

        return EmployeeContract::where('employee_id', $employeeId)
            ->when($exceptId, fn ($q) => $q->where('contract_id', '!=', $exceptId))
            ->get()
            ->first(fn (EmployeeContract $c) => ContractRules::effectiveStatus($c->lifecycle_status, (bool) $c->is_active, $c->end_date?->toDateString(), $today) === ContractRules::STATUS_ACTIVE);
    }

    /** Template terpilih manual (harus aktif dan sejenis) atau yang dipilih otomatis. */
    public function resolveTemplate($templateId, string $type, ?string $position, ?string $positionType = null): ?ContractTemplate
    {
        if ($templateId !== null && $templateId !== '') {
            $t = ContractTemplate::find((int) $templateId);
            if ($t && $t->contract_type === $type && $t->status === ContractTemplate::STATUS_ACTIVE) {
                return $t;
            }
            throw new \DomainException('That template is not available for this contract type.');
        }

        $picked = ContractRules::pickTemplate(
            ContractTemplate::where('status', ContractTemplate::STATUS_ACTIVE)->get()->map->toArray()->all(),
            $type,
            $position,
            $positionType
        );

        return $picked ? ContractTemplate::find($picked['id']) : null;
    }

    /** Nomor berikutnya: urut per (jenis, tahun) dengan kunci baris; dilewati bila (jarang) sudah dipakai data lama. */
    public function allocateNumber(string $type, Carbon $date): string
    {
        $year = (int) $date->format('Y');
        $cfg = config('hc_contract.number');

        return DB::transaction(function () use ($type, $year, $date, $cfg) {
            DB::table('contract_number_sequences')->insertOrIgnore([
                'contract_type' => $type, 'year' => $year, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now(),
            ]);
            $row = DB::table('contract_number_sequences')->where('contract_type', $type)->where('year', $year)->lockForUpdate()->first();

            $next = (int) $row->last_value;
            do {
                $number = ContractRules::formatNumber($type, $date, (string) $cfg['unit_code'], ++$next, (int) $cfg['digits'], $cfg['prefixes']);
            } while (EmployeeContract::where('contract_number', $number)->exists());

            DB::table('contract_number_sequences')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => now()]);

            return $number;
        });
    }

    // ── Template (tab Templates) ──────────────────────────────────────────

    /** Template aktif sejenis untuk pemilih di formulir kontrak. */
    public function templatesFor(string $type)
    {
        return ContractTemplate::where('contract_type', $type)->where('status', ContractTemplate::STATUS_ACTIVE)
            ->orderByDesc('is_system_default')->orderBy('name')->get(['id', 'name', 'position', 'position_type', 'is_system_default']);
    }

    /** @throws \DomainException */
    public function saveTemplate(?ContractTemplate $template, array $d, int $actorId): ContractTemplate
    {
        $type = ContractRules::normalizeType($d['contract_type'] ?? '');
        if ($type === null) {
            throw new \DomainException('Choose a contract type.');
        }

        $body = ContractHtmlSanitizer::clean($d['body_html'] ?? '');
        if (trim(strip_tags($body)) === '') {
            throw new \DomainException('The contract text cannot be empty.');
        }
        if ($unknown = ContractRules::unknownPlaceholders($body, ContractPlaceholders::keysFor($type))) {
            throw new \DomainException('Unknown placeholder' . (count($unknown) > 1 ? 's' : '') . ': {{' . implode('}}, {{', $unknown) . '}}. Use the Insert buttons, or check the spelling.');
        }

        $attrs = [
            'name'           => trim($d['name']),
            'description'    => $this->nullable($d['description'] ?? null),
            'body_html'      => $body,
            'use_letterhead' => (bool) ($d['use_letterhead'] ?? false),
            'signatory_employee_id' => $this->templateSigner($d['signatory_employee_id'] ?? null),
            'approver_employee_id'  => $this->templateSigner($d['approver_employee_id'] ?? null, 'The approver must be a signer'),
            ...self::pageColumns($d),
            'status'         => ($d['status'] ?? 'active') === 'inactive' ? ContractTemplate::STATUS_INACTIVE : ContractTemplate::STATUS_ACTIVE,
            'updated_by'     => $actorId,
        ];

        if ($template) {
            // Jenis bawaan sistem terkunci: kontrak yang sudah memakainya bergantung pada jenis itu.
            if ($template->is_system_default) {
                $attrs['status'] = ContractTemplate::STATUS_ACTIVE; // bawaan sistem selalu aktif: jadi cadangan terakhir
            } else {
                $attrs['contract_type'] = $type;
                $attrs['position'] = $type === ContractRules::TYPE_EXTERNAL ? null : $this->nullable($d['position'] ?? null);
                $attrs['position_type'] = $this->nullable($d['position_type'] ?? null);
            }
            $template->update($attrs);

            return $template->refresh();
        }

        $attrs['contract_type'] = $type;
        $attrs['position'] = $type === ContractRules::TYPE_EXTERNAL ? null : $this->nullable($d['position'] ?? null);
        $attrs['position_type'] = $this->nullable($d['position_type'] ?? null);
        $attrs['created_by'] = $actorId;

        return ContractTemplate::create($attrs);
    }

    /** Paper settings of a template as the structure PageSettings reads. */
    public static function templatePage(ContractTemplate $t): array
    {
        return PageSettings::store($t->page_size, $t->page_orientation, [
            'top' => $t->margin_top, 'right' => $t->margin_right, 'bottom' => $t->margin_bottom, 'left' => $t->margin_left,
        ]);
    }

    /** Template columns from the submitted form (margin boxes left empty stay empty = default). */
    public static function pageColumns(array $d): array
    {
        $p = PageSettings::store($d['page_size'] ?? null, $d['page_orientation'] ?? null, [
            'top' => $d['margin_top'] ?? null, 'right' => $d['margin_right'] ?? null, 'bottom' => $d['margin_bottom'] ?? null, 'left' => $d['margin_left'] ?? null,
        ]);

        return ['page_size' => $p['size'], 'page_orientation' => $p['orientation'], 'margin_top' => $p['margins']['top'], 'margin_right' => $p['margins']['right'],
            'margin_bottom' => $p['margins']['bottom'], 'margin_left' => $p['margins']['left']];
    }

    /** @throws \DomainException */
    private function templateSigner($id, string $what = 'The default signatory must be a signer'): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (!LetterService::signatoryOptions()->contains('id', (int) $id)) {
            throw new \DomainException($what . ' (Letter Templates → Settings → Signers).');
        }

        return (int) $id;
    }

    /** @throws \DomainException */
    public function deleteTemplate(ContractTemplate $template): void
    {
        if ($template->is_system_default) {
            throw new \DomainException('A system default template cannot be deleted. You can edit its text.');
        }
        $inUse = EmployeeContract::where('template_id', $template->id)->where('lifecycle_status', ContractRules::STATUS_DRAFT)->count();
        if ($inUse > 0) {
            throw new \DomainException("{$inUse} draft contract(s) still use this template. Switch them to another template, or set this one to Inactive instead.");
        }
        $template->delete();
    }

    // ── Dokumen ───────────────────────────────────────────────────────────

    /**
     * Dokumen siap tampil/cetak.
     *
     * @return array{title:string,number:string,html:string,letterhead:?Letterhead,use_letterhead:bool,template:?ContractTemplate,warnings:string[]}
     */
    public function document(EmployeeContract $contract, bool $maskSalary): array
    {
        $type = ContractRules::normalizeType($contract->contract_type) ?? ContractRules::TYPE_PKWT;
        $template = $contract->template_id ? ContractTemplate::find($contract->template_id) : null;
        $template ??= $this->resolveTemplate(null, $type, $contract->position);

        // Draft memakai teks template terkini; Active memakai bekuannya (body_html).
        $source = $contract->body_html ?: ($template?->body_html ?? '');
        $values = $this->values($contract, $type, $maskSalary);

        $letterhead = ($template?->use_letterhead ?? true) ? Letterhead::forLetter(self::LETTER_TYPE) : null;
        // A frozen (Active) contract keeps its own paper settings; a Draft follows its template.
        $page = PageSettings::resolve($contract->page_json ?: ($template ? self::templatePage($template) : null), $letterhead !== null);

        return [
            'page'           => $page,
            'title'          => $contract->contract_name ?: ($template?->name ?? 'Contract'),
            'number'         => $contract->contract_number,
            'html'           => ContractRules::render($source, $values),
            'letterhead'     => $letterhead,
            'use_letterhead' => (bool) ($template?->use_letterhead ?? true),
            'template'       => $template,
            'warnings'       => ContractRules::unknownPlaceholders($source, ContractPlaceholders::keysFor($type)),
        ];
    }

    /**
     * Generate the contract text from what is typed in the form — nothing is saved. The result is what the editor shows
     * under the form, so Legal / HR / RPMO can adjust it before the contract is stored.
     *
     * Every field is filled in except the live tokens (ContractRules::LIVE_TOKENS): the number is allocated on save and the
     * signatures / stamp only appear once the contract is not a Draft, so they stay as {{tokens}} and are filled when the
     * document is shown.
     *
     * @param  array<string,mixed>  $d       the form (as the controller validated it)
     * @param  object|null          $terms   commercial terms typed on the external form (a contract that does not exist yet)
     * @return array{html:string,template:ContractTemplate,type:string,use_letterhead:bool,page:array,warnings:string[]}
     * @throws \DomainException
     */
    public function previewDocument(int $employeeId, array $d, bool $canSalary, ?object $terms = null): array
    {
        $d = $this->fillFromEmployee($employeeId, $d);   // pratinjau memakai isian yang sama dengan yang akan disimpan
        $type = ContractRules::normalizeType($d['contract_type'] ?? '');
        if ($type === null) {
            throw new \DomainException('Choose a contract type first.');
        }
        $start = $d['start_date'] ?? null;
        $end = ($d['end_date'] ?? '') !== '' ? $d['end_date'] : null;
        if ($error = ContractRules::dateError($type, $start, $end, (int) config('hc_contract.pkwt_max_months', 60))) {
            throw new \DomainException($error);
        }

        $template = $this->resolveTemplate($d['template_id'] ?? null, $type, $d['position'] ?? null, $d['position_type'] ?? null);
        if ($template === null) {
            throw new \DomainException('No active template exists for this contract type and position type. Add one in the Templates tab first.');
        }

        // Company signatory: the one picked, else the default of the template.
        $signerId = ($d['signatory_employee_id'] ?? '') !== '' ? (int) $d['signatory_employee_id'] : ($template->signatory_employee_id ? (int) $template->signatory_employee_id : null);
        $person = $signerId ? LetterService::signatoryOptions()->firstWhere('id', $signerId) : null;

        $contract = new EmployeeContract([
            'employee_id'      => $employeeId,
            'contract_number'  => $d['contract_number'] ?? null,
            'contract_type'    => $type,
            'lifecycle_status' => ContractRules::STATUS_DRAFT,
            'is_active'        => false,
            'start_date'       => $start,
            'end_date'         => $end,
            'signed_date'      => ($d['signed_date'] ?? '') !== '' ? $d['signed_date'] : null,
            'position'         => $this->nullable($d['position'] ?? null),
            'department'       => $this->nullable($d['department'] ?? null),
            'work_location'    => $this->nullable($d['work_location'] ?? null),
            'work_volume'      => $this->nullable($d['work_volume'] ?? null),
            'notes'            => $this->nullable($d['notes'] ?? null),
            'signatory_employee_id' => $person['id'] ?? null,
            'signatory_name'   => $person['name'] ?? null,
            'signatory_title'  => $person['position'] ?? null,
        ]);
        if ($canSalary && ($d['salary'] ?? '') !== '') {
            $contract->salary = max(0, ContractRules::parseAmount($d['salary']));
        }
        if ($canSalary) {
            $contract->salary_components = ContractRules::cleanComponents($d['components'] ?? []);
        }

        $values = $this->values($contract, $type, !$canSalary, $terms);
        // The live tokens stay tokens; the contract number is one of them, so a not-yet-saved contract needs no fake number.
        foreach (ContractRules::LIVE_TOKENS as $token) {
            unset($values[$token]);
        }

        $source = $template->body_html;

        return [
            'html'           => ContractRules::render($source, $values, true),
            'template'       => $template,
            'type'           => $type,
            'use_letterhead' => (bool) $template->use_letterhead,
            'page'           => self::templatePage($template),
            'warnings'       => ContractRules::unknownPlaceholders($source, ContractPlaceholders::keysFor($type)),
        ];
    }

    /**
     * Nilai placeholder dari data kontrak + data induk karyawan. Hanya mengambil yang dibutuhkan.
     *
     * @return array<string,string|array{html:string}>
     */
    public function values(EmployeeContract $c, string $type, bool $maskSalary, ?object $terms = null): array
    {
        $id = (int) $c->employee_id;
        $basic = DB::table('employee_basic_data')->where('employee_id', $id)->first();
        $name = trim(($basic->first_name ?? '') . ' ' . ($basic->last_name ?? ''));
        $ident = DB::table('employee_identification')->where('employee_id', $id)->get();
        $address = DB::table('employee_address')->where('employee_id', $id)->whereIn('address_type', ['Home', 'primary'])
            ->orderByRaw("address_type = 'Home' desc")->first();
        $settings = DB::table('letter_settings')->first();

        $nik = $ident->first(fn ($r) => strtoupper((string) $r->identification_type) === 'KTP')?->identification_number;
        $npwp = $ident->first(fn ($r) => strtoupper((string) $r->identification_type) === 'NPWP')?->identification_number;

        $addressLine = $address ? collect([$address->street, $address->house_number, $address->rural_urban_village, $address->district, $address->city, $address->region, $address->postal_code])
            ->filter(fn ($p) => $p !== null && trim((string) $p) !== '')->implode(', ') : '';

        $signed = $c->signed_date ?? $c->start_date;
        $components = ContractRules::cleanComponents($c->salary_components);
        $total = ContractRules::totalSalary($c->salary !== null ? (float) $c->salary : null, $components);
        $masked = 'Rp •••••';

        $breakdown = ['<table style="width:100%"><tbody>'];
        $breakdown[] = '<tr><td style="width:60%">Gaji Pokok</td><td>' . ($maskSalary ? $masked : e(ContractRules::money((float) $c->salary))) . '</td></tr>';
        foreach ($components as $row) {
            $breakdown[] = '<tr><td>' . e($row['name']) . '</td><td>' . ($maskSalary ? $masked : e(ContractRules::money($row['amount']))) . '</td></tr>';
        }
        $breakdown[] = '</tbody></table>';

        $common = [
            'nomor_kontrak'        => $c->contract_number,
            'alamat'               => $addressLine,
            'npwp'                 => $npwp,
            'tanggal_mulai'        => $this->date($c->start_date),
            'tanggal_berakhir'     => $c->end_date ? $this->date($c->end_date) : 'tidak ditentukan',
            'tanggal_tanda_tangan' => $signed ? $this->signingSentence($signed) : '',
            'tanggal_surat'        => $signed ? Carbon::parse($signed)->locale('id')->translatedFormat('d F Y') : '',
            'lokasi_kerja'         => $c->work_location ?: ($basic->home_base ?? ''),
            'perusahaan'           => $settings->company_name ?? '',
            'kota_penandatanganan' => $settings->signing_city ?? '',
            // An empty signatory prints as blank lines to fill by hand, not as a stray "-".
            'penandatangan'        => $c->signatory_name ?: '………………………………',
            'jabatan_penandatangan' => $c->signatory_title ?: '………………………………',
            // Signature images from My Profile (HR Profile): shown only once the contract is not a Draft, like a signed letter.
            'meterai'              => ['html' => self::stampBoxHtml()],
            'meterai_tengah'       => ['html' => self::stampBoxHtml('center')],
            'meterai_kanan'        => ['html' => self::stampBoxHtml('right')],
            'tanda_tangan_penandatangan' => $this->signatureImage($c->signatory_employee_id ? (int) $c->signatory_employee_id : null, $c),
            'tanda_tangan_karyawan'      => $this->signatureImage($id, $c),
            'catatan'              => $c->notes,
        ];

        if ($type === ContractRules::TYPE_EXTERNAL) {
            $eng = DB::table('employee_engagement')->where('employee_id', $id)->first();
            // Ketentuan komersial per kontrak (menu External → External Contract) menggantikan nilai engagement bila ada.
            $terms ??= $c->contract_id ? DB::table('external_contract_terms')->where('contract_id', $c->contract_id)->first() : null;
            $scheme = $terms->engagement_scheme ?? ($eng->engagement_scheme ?? null);
            $unit = match (ExternalRules::schemeKey($scheme)) {
                'mandays' => 'per manday',
                'monthly' => 'per bulan',
                'hourly' => 'per jam',
                'fixed_price' => 'paket (fixed price)',
                default => '',
            };
            $rate = $terms && $terms->rate !== null
                ? ExternalRules::money($terms->rate, $terms->currency)
                : ($eng && $eng->rate !== null ? ContractRules::money((float) $eng->rate) : '');

            return $common + [
                'nama_konsultan'       => $name,
                'nomor_identitas'      => $nik,
                'tempat_tanggal_lahir' => trim(($basic->birth_place ?? '') . ($basic && $basic->birth_date ? ', ' . $this->date($basic->birth_date) : ''), ', '),
                'jenis_kelamin'        => match ($basic->gender ?? null) { 'Male', 'Laki-laki' => 'Laki-laki', 'Female' => 'Perempuan', default => '' },
                'vendor'               => $eng->vendor_partner ?? '',
                'perusahaan_prinsipal' => $eng->client_company ?? '',
                'spesialisasi'         => $c->position ?: ($eng->assignment_role ?? ''),
                'penugasan'            => ($terms->assignment ?? null) ?: ($eng->assignment_role ?? ($c->position ?: ($basic->position ?? ''))),
                'skema'                => $terms ? ExternalRules::schemeLabel($terms->engagement_scheme) : ($eng->engagement_scheme ?? ''),
                'tarif'                => $maskSalary ? $masked : $rate,
                'satuan_tagihan'       => $unit,
                'volume_kerja'         => (string) $c->work_volume,
                'jam_per_manday'       => $terms ? rtrim(rtrim(number_format((float) $terms->hours_per_manday, 1, ',', '.'), '0'), ',') . ' jam' : '',
                'termin_pembayaran'    => $terms ? $terms->payment_term_days . ' hari' : '',
            ];
        }

        return $common + [
            'jenis_kontrak'        => ContractRules::TYPES[$type] ?? $type,
            'nama_karyawan'        => $name,
            'nik'                  => $nik,
            'tempat_tanggal_lahir' => trim(($basic->birth_place ?? '') . ($basic && $basic->birth_date ? ', ' . $this->date($basic->birth_date) : ''), ', '),
            'jenis_kelamin'        => match ($basic->gender ?? null) { 'Male', 'Laki-laki' => 'Laki-laki', 'Female' => 'Perempuan', default => '' },
            'posisi'               => $c->position ?: ($basic->position ?? ''),
            'jabatan'              => $c->position ?: ($basic->position ?? ''),
            'departemen'           => $c->department ?: ($basic->department ?? ''),
            'gaji'                 => $maskSalary ? $masked : ContractRules::money($total),
            'gaji_pokok'           => $maskSalary ? $masked : ContractRules::money((float) $c->salary),
            'tunjangan'            => $maskSalary ? $masked : ($components ? ContractRules::money(array_sum(array_column($components, 'amount'))) : '-'),
            'rincian_gaji'         => ['html' => implode('', $breakdown)],
        ];
    }

    /**
     * The drawn / uploaded signature of an employee (My Profile → Photo, signature & HR information) as an inline image for the
     * PDF. Empty for a Draft or when the person has none — the space stays free for a signature by hand.
     *
     * @return array{html:string}
     */
    private function signatureImage(?int $employeeId, EmployeeContract $c): array
    {
        $empty = ['html' => ''];
        $status = ContractRules::effectiveStatus($c->lifecycle_status, (bool) $c->is_active, $c->end_date?->toDateString(), now()->toDateString());
        if (!$employeeId || $status === ContractRules::STATUS_DRAFT) {
            return $empty;
        }

        $path = \App\Models\EmployeeHrProfile::signaturePathOf($employeeId);
        if (!$path) {
            return $empty;
        }
        $disk = \Illuminate\Support\Facades\Storage::disk(\App\Models\EmployeeHrProfile::FILE_DISK);

        return ['html' => '<img src="data:' . $disk->mimeType($path) . ';base64,' . base64_encode($disk->get($path)) . '" style="height:54pt" alt="">'];
    }

    /** The dashed box where the stamp (meterai) is stuck; size and wording come from config/hc_contract.php. */
    public static function stampBoxHtml(string $align = 'left'): string
    {
        $c = config('hc_contract.stamp');

        return sprintf(
            '<table align="%s" style="width:%.1fcm;border:1pt dashed #888;margin:0"><tbody><tr><td style="height:%.1fcm;text-align:center;vertical-align:middle;font-size:8pt;color:#777">%s<br>%s</td></tr></tbody></table>',
            in_array($align, ['left', 'center', 'right'], true) ? $align : 'left', $c['width_cm'], $c['height_cm'], e($c['label']), e($c['value'])
        );
    }

    // ── Salinan bermeterai yang diunggah (metode 1) ─────────────────────

    /**
     * Simpan salinan kontrak yang sudah ditandatangani + diberi meterai. Hanya PDF (diperiksa isinya, bukan hanya namanya);
     * disimpan di disk privat; menggantikan salinan sebelumnya.
     *
     * @throws \DomainException
     */
    public function storeSignedCopy(EmployeeContract $contract, \Illuminate\Http\UploadedFile $file, int $actorId): EmployeeContract
    {
        $head = (string) @file_get_contents($file->getRealPath(), false, null, 0, 5);
        if ($head !== '%PDF-') {
            throw new \DomainException('Upload the stamped contract as a PDF file.');
        }
        if (($contract->lifecycle_status ?? '') === ContractRules::STATUS_DRAFT) {
            throw new \DomainException('A Draft cannot have a stamped copy. Set the contract to Active first.');
        }
        $this->assertStampedCopyFree($contract);

        $old = $contract->signed_file_path;
        $path = $file->storeAs('contracts/' . $contract->contract_id, now()->format('YmdHis') . '-' . \Illuminate\Support\Str::random(8) . '.pdf', 'local');
        $contract->update([
            'signed_file_path' => $path,
            'signed_file_name' => mb_substr(preg_replace('/[^\w.\- ]+/u', '_', $file->getClientOriginalName()), 0, 200),
            'signed_file_at'   => now(),
            'signed_file_by'   => $actorId,
            'stamp_method'     => 'physical',
        ]);
        if ($old) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($old);
        }

        return $contract->refresh();
    }

    /**
     * The stamped PDF is what is being signed: while it is with the employee, Legal or the approver — or released — it stays.
     * Withdraw it from signing, or wait for a revision request, to change it.
     *
     * @throws \DomainException
     */
    private function assertStampedCopyFree(EmployeeContract $contract): void
    {
        if (in_array($contract->sign_stage, [SigningRules::WITH_EMPLOYEE, SigningRules::WITH_LEGAL, SigningRules::WITH_APPROVER, SigningRules::APPROVED, SigningRules::RELEASED], true)) {
            throw new \DomainException('This contract is in the signing process, so its stamped copy cannot be changed. Withdraw it from signing first.');
        }
    }

    public function removeSignedCopy(EmployeeContract $contract): void
    {
        $this->assertStampedCopyFree($contract);
        if ($contract->signed_file_path) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($contract->signed_file_path);
        }
        $contract->update(['signed_file_path' => null, 'signed_file_name' => null, 'signed_file_at' => null, 'signed_file_by' => null, 'stamp_method' => null]);
    }

    private function date($value): string
    {
        return Carbon::parse($value)->locale('id')->translatedFormat('j F Y');
    }

    /** "Senin, tanggal 27 bulan Juli tahun 2026" — dipasang setelah "pada hari". */
    private function signingSentence($value): string
    {
        $d = Carbon::parse($value)->locale('id');

        return $d->translatedFormat('l') . ', tanggal ' . $d->format('j') . ' bulan ' . $d->translatedFormat('F') . ' tahun ' . $d->format('Y');
    }

    /** Nilai contoh untuk pratinjau template (tidak membaca data karyawan mana pun). */
    public static function sampleValues(string $type): array
    {
        $common = [
            'nomor_kontrak' => 'SPKWT/2026/10/010001', 'alamat' => 'Jalan Simulasi No. 1, Kota Contoh', 'npwp' => '00.000.000.0-000.000',
            'tanggal_mulai' => '1 Oktober 2026', 'tanggal_berakhir' => '31 Maret 2027', 'tanggal_tanda_tangan' => 'Kamis, tanggal 1 bulan Oktober tahun 2026', 'tanggal_surat' => '01 Oktober 2026', 'lokasi_kerja' => 'Yogyakarta',
            'perusahaan' => 'PT Contoh Perusahaan', 'kota_penandatanganan' => 'Yogyakarta', 'penandatangan' => 'Nama Penandatangan',
            'jabatan_penandatangan' => 'Jabatan Penandatangan', 'catatan' => 'Catatan contoh',
            'tanda_tangan_penandatangan' => ['html' => ''], 'tanda_tangan_karyawan' => ['html' => ''], 'meterai' => ['html' => self::stampBoxHtml()], 'meterai_tengah' => ['html' => self::stampBoxHtml('center')], 'meterai_kanan' => ['html' => self::stampBoxHtml('right')],
        ];

        if ($type === ContractRules::TYPE_EXTERNAL) {
            return $common + [
                'nama_konsultan' => 'Nama Konsultan', 'nomor_identitas' => '0000000000000000', 'vendor' => 'Vendor Contoh',
                'perusahaan_prinsipal' => 'Klien Contoh', 'spesialisasi' => 'SAP Consultant', 'penugasan' => 'Proyek Contoh',
                'skema' => 'Mandays', 'tarif' => 'Rp 2.500.000', 'satuan_tagihan' => 'per manday', 'volume_kerja' => '20,00 mandays', 'jam_per_manday' => '8 jam', 'termin_pembayaran' => '14 hari', 'tempat_tanggal_lahir' => 'Kota Contoh, 5 Januari 1990', 'jenis_kelamin' => 'Laki-laki',
            ];
        }

        return $common + [
            'jenis_kontrak' => ContractRules::TYPES[$type] ?? $type, 'nama_karyawan' => 'Nama Karyawan', 'nik' => '0000000000000000',
            'tempat_tanggal_lahir' => 'Kota Contoh, 5 Januari 1994', 'jenis_kelamin' => 'Perempuan', 'posisi' => 'Management Trainee',
            'jabatan' => 'Management Trainee', 'departemen' => 'Human Capital',
            'gaji' => 'Rp 5.750.000', 'gaji_pokok' => 'Rp 4.500.000', 'tunjangan' => 'Rp 1.250.000', 'rincian_gaji' => ['html' => '<table style="width:100%"><tbody><tr><td style="width:60%">Gaji Pokok</td><td>Rp 4.500.000</td></tr><tr><td>Tunjangan Transportasi</td><td>Rp 750.000</td></tr><tr><td>Uang Makan</td><td>Rp 500.000</td></tr></tbody></table>'],
        ];
    }
}
