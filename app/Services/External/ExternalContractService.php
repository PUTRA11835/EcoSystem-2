<?php

namespace App\Services\External;

use App\Models\EmployeeContract;
use App\Services\Contracts\ContractService;
use App\Support\Contracts\ContractRules;
use App\Support\External\ExternalRules;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * External → External Contract: kontrak konsultan eksternal.
 *
 * Satu kontrak = satu baris `employee_contract` berjenis EXTERNAL, dibuat/diubah lewat ContractService (nomor PKS, template beku,
 * aturan status, pagar kesiapan data, dokumen PDF) — sama persis dengan menu Contract. Layanan ini hanya menambah:
 *   · ketentuan komersial per kontrak (`external_contract_terms`: skema, tarif, satuan tagihan, jam per manday, prorata, termin);
 *   · daftar khusus kontrak konsultan dengan filter dan ringkasan.
 *
 * Tarif hanya dibaca/ditulis pemegang `general.contracts.salary` (parameter `$canRate`).
 */
class ExternalContractService
{
    private const TYPE_ALIASES = ['EXTERNAL', 'EXT', 'PKS'];

    public function __construct(private ContractService $contracts) {}

    // ── Daftar ────────────────────────────────────────────────────────────

    /**
     * @param  array{search?:string,scheme?:string,status?:string}  $filters
     */
    public function listing(array $filters, int $perPage, bool $canRate): LengthAwarePaginator
    {
        $today = now()->toDateString();

        $page = $this->baseQuery($canRate)
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']) . '%';
                $q->where(fn ($s) => $s
                    ->whereRaw("CONCAT_WS(' ', b.first_name, b.last_name) like ?", [$like])
                    ->orWhere('e.eci', 'like', $like)
                    ->orWhere('c.contract_number', 'like', $like)
                    ->orWhere('c.position', 'like', $like));
            })
            ->when(in_array($filters['scheme'] ?? '', ['mandays', 'monthly', 'fixed_price', 'hourly'], true),
                fn ($q) => $q->where('t.engagement_scheme', $filters['scheme']))
            // A custom contract type chosen in Settings is filed under its own code.
            ->when(($filters['kind'] ?? '') !== '', fn ($q) => $q->where('c.contract_kind', $filters['kind']))
            ->tap(fn ($q) => $this->contracts->applyStatusFilter($q, (string) ($filters['status'] ?? ''), $today))
            ->orderByDesc('c.start_date')->orderByDesc('c.contract_id')
            ->paginate($perPage)->withQueryString();

        $page->getCollection()->transform(fn ($row) => $this->decorate($row, $today));

        return $page;
    }

    /** Kartu ringkasan: semua, aktif berjalan, draft, dan yang memakai template. */
    public function summary(): array
    {
        $today = now()->toDateString();

        $row = $this->baseQuery(false, false)->selectRaw(
            "COUNT(*) as total,
             SUM(IFNULL(c.lifecycle_status,'') NOT IN ('draft','terminated') AND c.is_active = 1 AND (c.end_date IS NULL OR c.end_date >= ?)) as active,
             SUM(c.lifecycle_status = 'draft') as draft,
             SUM(c.template_id IS NOT NULL) as templated",
            [$today]
        )->first();

        return ['total' => (int) $row->total, 'active' => (int) $row->active, 'draft' => (int) $row->draft, 'templated' => (int) $row->templated];
    }

    private function baseQuery(bool $canRate, bool $columns = true)
    {
        return DB::table('employee_contract as c')
            ->join('employee as e', 'e.employee_id', '=', 'c.employee_id')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->leftJoin('external_contract_terms as t', 't.contract_id', '=', 'c.contract_id')
            ->leftJoin('contract_templates as tpl', 'tpl.id', '=', 'c.template_id')
            ->whereIn(DB::raw('UPPER(c.contract_type)'), self::TYPE_ALIASES)
            // Hanya konsultan External: kontrak jenis EXTERNAL pada karyawan Internal (data uji lama) tetap terlihat di menu Contract.
            ->where('b.employee_type', 'External')
            ->when($columns, fn ($q) => $q->select(array_merge([
                'c.contract_id', 'c.employee_id', 'c.contract_number', 'c.contract_name', 'c.start_date', 'c.end_date', 'c.is_active',
                'c.lifecycle_status', 'c.position as contract_position', 'c.contract_kind', 'c.signed_file_path',
                'e.eci', 'b.first_name', 'b.last_name', 'b.nick_name', 'b.position as employee_position',
                'tpl.name as template_name', 't.engagement_scheme', 't.billing_unit',
            ], $canRate ? ['t.rate', 't.currency'] : [])));
    }

    private function decorate(object $row, string $today): object
    {
        $row->name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ($row->nick_name ?: $row->eci);
        $row->position = $row->contract_position ?: $row->employee_position;
        $row->effective_status = ContractRules::effectiveStatus($row->lifecycle_status, (bool) $row->is_active, $row->end_date, $today);
        $row->scheme_key = ExternalRules::schemeKey($row->engagement_scheme);
        $row->scheme_label = $row->scheme_key !== null ? ExternalRules::schemeLabel($row->scheme_key) : null;
        $row->ending_soon = $row->effective_status === ContractRules::STATUS_ACTIVE && $row->end_date
            && $row->end_date <= now()->addDays((int) \App\Services\Management\EmployeeMasterData::rule('contract', 'ending_soon_days'))->toDateString();
        $row->has_signed = !empty($row->signed_file_path);
        if (property_exists($row, 'rate')) {
            $row->rate_text = $row->rate !== null ? ExternalRules::money($row->rate, $row->currency) : null;
            $row->rate_unit = $row->billing_unit ?: ltrim(ExternalRules::rateUnit($row->scheme_key), '/ ');
        }

        return $row;
    }

    // ── Satu konsultan ────────────────────────────────────────────────────

    /**
     * Riwayat kontrak EXTERNAL satu konsultan (terbaru dulu), lengkap dengan ketentuan komersialnya.
     *
     * @return array<int,EmployeeContract>
     */
    public function history(int $employeeId, bool $canRate): array
    {
        $today = now()->toDateString();
        $terms = DB::table('external_contract_terms')->whereIn('contract_id', EmployeeContract::where('employee_id', $employeeId)->pluck('contract_id'))
            ->get()->keyBy('contract_id');

        return EmployeeContract::where('employee_id', $employeeId)
            ->whereIn(DB::raw('UPPER(contract_type)'), self::TYPE_ALIASES)
            ->orderByDesc('start_date')->orderByDesc('contract_id')->get()
            ->map(function (EmployeeContract $c) use ($today, $terms, $canRate) {
                $t = $terms->get($c->contract_id);
                $c->setAttribute('effective_status', ContractRules::effectiveStatus($c->lifecycle_status, (bool) $c->is_active, $c->end_date?->toDateString(), $today));
                $c->setAttribute('terms', $t);
                $c->setAttribute('scheme_key', ExternalRules::schemeKey($t->engagement_scheme ?? null));
                $c->setAttribute('rate_text', $canRate && $t && $t->rate !== null ? ExternalRules::money($t->rate, $t->currency) : null);

                return $c;
            })->all();
    }

    public function templates()
    {
        return $this->contracts->templatesFor(ContractRules::TYPE_EXTERNAL);
    }

    // ── Simpan ────────────────────────────────────────────────────────────

    /**
     * Buat kontrak konsultan (draft atau aktif). Ketentuan divalidasi SEBELUM kontrak dibuat; keduanya tersimpan dalam satu transaksi.
     *
     * @param  array<string,mixed>  $d  data formulir yang sudah divalidasi controller
     * @throws \DomainException
     */
    public function create(int $employeeId, array $d, int $actorId, bool $canRate): EmployeeContract
    {
        $terms = ExternalRules::cleanTerms($d, $canRate);

        return DB::transaction(function () use ($employeeId, $d, $actorId, $terms) {
            $contract = $this->contracts->create($employeeId, $this->contractData($d), $actorId, false);
            $this->saveTerms($contract, $terms);

            return $contract;
        });
    }

    /**
     * @param  array<string,mixed>  $d
     * @throws \DomainException
     */
    public function update(EmployeeContract $contract, array $d, bool $canRate): EmployeeContract
    {
        if (ContractRules::normalizeType($contract->contract_type) !== ContractRules::TYPE_EXTERNAL) {
            throw new \DomainException('This is not an external consultant contract.');
        }
        $terms = ExternalRules::cleanTerms($d, $canRate);

        return DB::transaction(function () use ($contract, $d, $terms) {
            $contract = $this->contracts->update($contract, $this->contractData($d) + ['contract_number' => $d['contract_number'] ?? $contract->contract_number], false);
            $this->saveTerms($contract, $terms);

            return $contract;
        });
    }

    /** Kolom kontrak dari formulir External Contract (jenis selalu EXTERNAL). */
    private function contractData(array $d): array
    {
        return [
            'contract_type'         => ContractRules::TYPE_EXTERNAL,
            'status'                => $d['status'] ?? ContractRules::STATUS_DRAFT,
            'template_id'           => $d['template_id'] ?? null,
            'start_date'            => $d['start_date'] ?? null,
            'end_date'              => $d['end_date'] ?? null,
            'signed_date'           => $d['signed_date'] ?? null,
            'work_location'         => $d['work_location'] ?? null,
            'work_volume'           => $d['work_volume'] ?? null,
            'notes'                 => $d['notes'] ?? null,
            'signatory_employee_id' => $d['signatory_employee_id'] ?? null,
            'position'              => $d['position'] ?? null,
            'position_type'         => $d['position_type'] ?? null,
            'contract_kind'         => $d['contract_kind'] ?? null,
            // A generated document that was adjusted by hand travels with the form (see ContractService::attributes).
            'body_html'             => $d['body_html'] ?? null,
            'body_customized'       => $d['body_customized'] ?? null,
            'reset_body'            => $d['reset_body'] ?? null,
            'page_size'             => $d['page_size'] ?? null,
            'page_orientation'      => $d['page_orientation'] ?? null,
            'margin_top'            => $d['margin_top'] ?? null,
            'margin_right'          => $d['margin_right'] ?? null,
            'margin_bottom'         => $d['margin_bottom'] ?? null,
            'margin_left'           => $d['margin_left'] ?? null,
        ];
    }

    /**
     * The contract text generated from the external form, not saved. The commercial terms typed on the form are used even
     * though the contract does not exist yet.
     *
     * @param  array<string,mixed>  $d
     * @return array<string,mixed>  see ContractService::previewDocument
     * @throws \DomainException
     */
    public function preview(int $employeeId, array $d, bool $canRate): array
    {
        $terms = ExternalRules::cleanTerms($d, $canRate);
        $terms += ['rate' => null, 'currency' => 'IDR'];

        // Without the rate permission the rate stays masked in the generated text, as it does in the document.
        return $this->contracts->previewDocument($employeeId, $this->contractData($d), $canRate, (object) $terms);
    }

    /** @param  array<string,mixed>  $terms  baris dari ExternalRules::cleanTerms (tanpa tarif bila tak berizin → tarif lama dipertahankan) */
    private function saveTerms(EmployeeContract $contract, array $terms): void
    {
        $existing = DB::table('external_contract_terms')->where('contract_id', $contract->contract_id)->exists();

        if ($existing) {
            DB::table('external_contract_terms')->where('contract_id', $contract->contract_id)->update($terms + ['updated_at' => now()]);

            return;
        }

        DB::table('external_contract_terms')->insert($terms + [
            'contract_id' => $contract->contract_id, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
