<?php

namespace App\Services\Contracts;

use App\Models\ContractTemplate;
use App\Models\EmployeeContract;
use App\Models\Letterhead;
use App\Services\Letters\LetterService;
use App\Support\Contracts\ContractHtmlSanitizer;
use App\Support\Contracts\ContractPlaceholders;
use App\Support\Contracts\ContractRules;
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
            ->when(isset(ContractRules::TYPES[$filters['type'] ?? '']), fn ($q) => $q
                ->whereIn(DB::raw('UPPER(c.contract_type)'), $this->typeAliases($filters['type'])))
            ->tap(fn ($q) => $this->applyStatusFilter($q, $filters['status'] ?? '', $today))
            ->orderBy('b.first_name')->orderBy('e.employee_id')
            ->paginate($perPage)->withQueryString();

        $page->getCollection()->transform(fn ($row) => $this->decorate($row, $today));

        return $page;
    }

    /** Hitungan untuk kartu ringkasan di atas daftar. */
    public function summary(): array
    {
        $today = now()->toDateString();
        $soon = now()->addDays((int) config('hc_contract.expiring_soon_days', 30))->toDateString();

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
            ->when($columns, fn ($q) => $q->select([
                'e.employee_id', 'e.eci', 'b.first_name', 'b.last_name', 'b.nick_name',
                'b.position as employee_position', 'b.department as employee_department', 'b.employee_type',
                'c.contract_id', 'c.contract_number', 'c.contract_type', 'c.start_date', 'c.end_date',
                'c.is_active', 'c.lifecycle_status', 'c.position as contract_position', 'c.signed_file_path',
            ]));
    }

    /** Cermin SQL dari ContractRules::effectiveStatus(). */
    private function applyStatusFilter($q, string $status, string $today): void
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
            && $row->end_date <= now()->addDays((int) config('hc_contract.expiring_soon_days', 30))->toDateString();

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
                // Dokumen dikenali dari jenis/judul/nama berkas yang memuat kata kuncinya (KTP, KK).
                'attachment'     => $attachments->contains(fn ($r) => (bool) preg_match('/\b' . preg_quote($arg, '/') . '\b/i',
                    trim(($r->document_type ?? '') . ' ' . ($r->document_title ?? '') . ' ' . ($r->file_name ?? '')))),
                default          => false,
            };
        }

        return ContractRules::readiness($items, $ok);
    }

    // ── Simpan ────────────────────────────────────────────────────────────

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

        return DB::transaction(function () use ($employeeId, $d, $actorId, $canSalary) {
            $attrs = $this->attributes($employeeId, null, $d, $canSalary);
            $attrs['employee_id'] = $employeeId;
            $attrs['created_by'] = $actorId;
            $attrs['contract_number'] = $this->allocateNumber($attrs['contract_type'], now());

            return EmployeeContract::create($attrs);
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

            return $contract->refresh();
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

        $template = $this->resolveTemplate($d['template_id'] ?? null, $type, $d['position'] ?? null);
        if ($template === null) {
            throw new \DomainException('No active template exists for this contract type. Add one in the Templates tab first.');
        }

        $attrs = [
            'contract_type' => $type,
            'contract_name' => $template->name,
            'lifecycle_status' => $status,
            'is_active'     => ContractRules::isActiveFor($status),
            'start_date'    => $start,
            'end_date'      => $end,
            'signed_date'   => ($d['signed_date'] ?? '') !== '' ? $d['signed_date'] : null,
            'contract_date' => ($d['signed_date'] ?? '') !== '' ? $d['signed_date'] : $start,
            'position'      => $this->nullable($d['position'] ?? null),
            'department'    => $this->nullable($d['department'] ?? null),
            'work_location' => $this->nullable($d['work_location'] ?? null),
            'notes'         => $this->nullable($d['notes'] ?? null),
            'work_volume'   => $this->nullable($d['work_volume'] ?? null),
            'template_id'   => $template->id,
            // Draft memakai teks template terkini; Active membekukannya (sekali, atau ulang bila template-nya diganti).
            'body_html'     => $status === ContractRules::STATUS_ACTIVE
                ? (($existing && $existing->body_html && (int) $existing->template_id === $template->id) ? $existing->body_html : $template->body_html)
                : ($status === ContractRules::STATUS_DRAFT ? null : ($existing?->body_html)),
        ];

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
    public function resolveTemplate($templateId, string $type, ?string $position): ?ContractTemplate
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
            $position
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
            ->orderByDesc('is_system_default')->orderBy('name')->get(['id', 'name', 'position', 'is_system_default']);
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
            }
            $template->update($attrs);

            return $template->refresh();
        }

        $attrs['contract_type'] = $type;
        $attrs['position'] = $type === ContractRules::TYPE_EXTERNAL ? null : $this->nullable($d['position'] ?? null);
        $attrs['created_by'] = $actorId;

        return ContractTemplate::create($attrs);
    }

    /** @throws \DomainException */
    private function templateSigner($id): ?int
    {
        if ($id === null || $id === '') {
            return null;
        }
        if (!LetterService::signatoryOptions()->contains('id', (int) $id)) {
            throw new \DomainException('The default signatory must be a signer (Letter Templates → Settings → Signers).');
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

        return [
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
     * Nilai placeholder dari data kontrak + data induk karyawan. Hanya mengambil yang dibutuhkan.
     *
     * @return array<string,string|array{html:string}>
     */
    public function values(EmployeeContract $c, string $type, bool $maskSalary): array
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
            $unit = match (strtolower((string) ($eng->engagement_scheme ?? ''))) {
                'mandays', 'man-days', 'manday' => 'per manday',
                'manmonth', 'man-month', 'monthly' => 'per bulan',
                'hourly' => 'per jam',
                'fixed price', 'fixed' => 'paket (fixed price)',
                default => '',
            };

            return $common + [
                'nama_konsultan'       => $name,
                'nomor_identitas'      => $nik,
                'tempat_tanggal_lahir' => trim(($basic->birth_place ?? '') . ($basic && $basic->birth_date ? ', ' . $this->date($basic->birth_date) : ''), ', '),
                'jenis_kelamin'        => match ($basic->gender ?? null) { 'Male', 'Laki-laki' => 'Laki-laki', 'Female' => 'Perempuan', default => '' },
                'vendor'               => $eng->vendor_partner ?? '',
                'perusahaan_prinsipal' => $eng->client_company ?? '',
                'spesialisasi'         => $c->position ?: ($eng->assignment_role ?? ''),
                'penugasan'            => $eng->assignment_role ?? ($c->position ?: ($basic->position ?? '')),
                'skema'                => $eng->engagement_scheme ?? '',
                'tarif'                => $maskSalary ? $masked : ($eng && $eng->rate !== null ? ContractRules::money((float) $eng->rate) : ''),
                'satuan_tagihan'       => $unit,
                'volume_kerja'         => (string) $c->work_volume,
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

    public function removeSignedCopy(EmployeeContract $contract): void
    {
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
                'skema' => 'Mandays', 'tarif' => 'Rp 2.500.000', 'satuan_tagihan' => 'per manday', 'volume_kerja' => '20,00 mandays', 'tempat_tanggal_lahir' => 'Kota Contoh, 5 Januari 1990', 'jenis_kelamin' => 'Laki-laki',
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
