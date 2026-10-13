<?php

namespace App\Services\External;

use App\Support\External\ExternalRules;
use App\Support\Initials;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * External → Consultants: daftar dan profil konsultan eksternal.
 *
 * DATA MASIH SATU: konsultan = karyawan Master Employee berjenis `External` (employee_basic_data.employee_type) + blok
 * Engagement-nya (employee_engagement, HC-D47). Halaman ini hanya MEMBACA — data tetap diubah di Master Employee, sehingga
 * tak ada dua sumber kebenaran. Kelak, bila internal dan eksternal dipisah, hanya kelas ini yang perlu diganti.
 *
 * Tarif hanya dibaca bila pemanggil memegang `external.section.engagement_rate.view`; tanpa itu kolomnya tak pernah diambil.
 */
class ExternalConsultantService
{
    // ── Daftar ────────────────────────────────────────────────────────────

    /**
     * @param  array{search?:string,scheme?:string,status?:string}  $filters
     */
    public function listing(array $filters, int $perPage, bool $withRate): LengthAwarePaginator
    {
        $today = now()->toDateString();

        $page = $this->baseQuery($withRate)
            ->when(($filters['search'] ?? '') !== '', function ($q) use ($filters) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']) . '%';
                $q->where(fn ($s) => $s
                    ->whereRaw("CONCAT_WS(' ', b.first_name, b.last_name) like ?", [$like])
                    ->orWhere('b.nick_name', 'like', $like)
                    ->orWhere('e.eci', 'like', $like)
                    ->orWhere('g.vendor_partner', 'like', $like)
                    ->orWhere('g.client_company', 'like', $like)
                    ->orWhere('g.assignment_role', 'like', $like)
                    ->orWhere('b.position', 'like', $like));
            })
            ->tap(fn ($q) => $this->applySchemeFilter($q, (string) ($filters['scheme'] ?? '')))
            ->when(($filters['status'] ?? '') === 'active', fn ($q) => $q->where('e.is_active', 1))
            ->when(($filters['status'] ?? '') === 'inactive', fn ($q) => $q->where('e.is_active', 0))
            ->orderBy('b.first_name')->orderBy('e.employee_id')
            ->paginate($perPage)->withQueryString();

        $contacts = $this->contacts($page->getCollection()->pluck('employee_id')->all());
        $page->getCollection()->transform(fn ($row) => $this->decorate($row, $today, $contacts[$row->employee_id] ?? null));

        return $page;
    }

    /** Hitungan kartu ringkasan di atas daftar. */
    public function summary(): array
    {
        $rows = $this->baseQuery(false)->get(['e.employee_id', 'e.is_active', 'g.engagement_scheme']);

        $count = ['total' => $rows->count(), 'active' => 0, 'mandays' => 0, 'monthly' => 0, 'other' => 0, 'unset' => 0];
        foreach ($rows as $row) {
            $row->is_active ? $count['active']++ : null;
            $key = ExternalRules::schemeKey($row->engagement_scheme);
            match (true) {
                $key === 'mandays' => $count['mandays']++,
                $key === 'monthly' => $count['monthly']++,
                $key === null      => $count['unset']++,
                default            => $count['other']++,
            };
        }

        return $count;
    }

    /**
     * Pilihan konsultan AKTIF untuk pemilih di formulir (kontrak, surat) beserta data yang mengisi formulir otomatis.
     *
     * @return array<int,array<string,mixed>>
     */
    public function options(): array
    {
        return $this->baseQuery(false)->where('e.is_active', 1)->orderBy('b.first_name')->get()
            ->map(fn ($r) => [
                'id'       => (int) $r->employee_id,
                'eci'      => $r->eci,
                'name'     => $this->fullName($r),
                'position' => $r->position,
                'role'     => $r->assignment_role,
                'client'   => $r->client_company,
                'vendor'   => $r->vendor_partner,
                'scheme'   => ExternalRules::schemeKey($r->engagement_scheme),
                'start'    => $r->engagement_start,
                'end'      => $r->engagement_end,
            ])->all();
    }

    public function find(int $employeeId, bool $withRate = false): ?object
    {
        $row = $this->baseQuery($withRate)->where('e.employee_id', $employeeId)->first();

        return $row ? $this->decorate($row, now()->toDateString(), $this->contacts([$employeeId])[$employeeId] ?? null) : null;
    }

    // ── Profil (hanya baca) ───────────────────────────────────────────────

    /**
     * Blok profil dari tabel induk. Tiap blok hanya diambil bila pemanggil memegang izin seksinya di Master Employee
     * (`$allow` = ['identification' => bool, 'address' => bool, 'bank' => bool, 'attachment' => bool]).
     *
     * @param  array<string,bool>  $allow
     */
    public function profile(int $employeeId, array $allow): array
    {
        $basic = DB::table('employee_basic_data')->where('employee_id', $employeeId)->first();
        $out = ['basic' => $basic, 'identification' => null, 'address' => null, 'bank' => null, 'attachments' => null];

        if (!empty($allow['identification'])) {
            $ident = DB::table('employee_identification')->where('employee_id', $employeeId)->get();
            $of = fn (string $type) => $ident->first(fn ($r) => strtoupper((string) $r->identification_type) === $type)?->identification_number;
            $out['identification'] = ['nik' => $of('KTP'), 'npwp' => $of('NPWP'), 'passport' => $of('PASSPORT') ?: $of('PASPOR')];
        }

        if (!empty($allow['address'])) {
            $home = DB::table('employee_address')->where('employee_id', $employeeId)
                ->orderByRaw("address_type = 'Home' desc")->orderByDesc('address_id')->first();
            $out['address'] = $home ? [
                'line' => collect([$home->street, $home->house_number, $home->rural_urban_village, $home->district])->filter(fn ($p) => trim((string) $p) !== '')->implode(', '),
                'city' => collect([$home->city, $home->region, $home->postal_code])->filter(fn ($p) => trim((string) $p) !== '')->implode(', '),
                'phone' => $home->cell_phone ?: $home->telephone,
                'email_work' => $home->email_work,
                'email_personal' => $home->email_personal,
            ] : [];
        }

        if (!empty($allow['bank'])) {
            $bank = DB::table('employee_bank')->where('employee_id', $employeeId)->orderByDesc('bank_id')->first();
            $out['bank'] = $bank ? ['bank' => $bank->bank_name, 'number' => $bank->account_number, 'holder' => $bank->account_holder] : [];
        }

        if (!empty($allow['attachment'])) {
            $out['attachments'] = DB::table('employee_attachment')->where('employee_id', $employeeId)->orderByDesc('attachment_id')
                ->get(['attachment_id', 'document_type', 'document_title', 'file_name', 'file_size', 'created_at']);
        }

        return $out;
    }

    // ── internal ──────────────────────────────────────────────────────────

    private function baseQuery(bool $withRate)
    {
        return DB::table('employee as e')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 'e.employee_id')
            ->leftJoin('employee_engagement as g', 'g.employee_id', '=', 'e.employee_id')
            ->where('b.employee_type', 'External')
            ->where(fn ($q) => $q->where('b.deletion_flag', 0)->orWhereNull('b.deletion_flag'))
            ->select(array_merge([
                'e.employee_id', 'e.eci', 'e.is_active', 'b.first_name', 'b.last_name', 'b.nick_name', 'b.position', 'b.department',
                'g.engagement_scheme', 'g.vendor_partner', 'g.client_company', 'g.assignment_role', 'g.managed_by',
                'g.start_date as engagement_start', 'g.end_date as engagement_end',
            ], $withRate ? ['g.rate', 'g.currency'] : []));
    }

    /** Filter skema: cocok dengan kunci maupun label yang tersimpan ("mandays" / "Mandays"). */
    private function applySchemeFilter($q, string $scheme): void
    {
        if ($scheme === 'unset') {
            $q->where(fn ($s) => $s->whereNull('g.engagement_scheme')->orWhere('g.engagement_scheme', ''));

            return;
        }
        if ($scheme === 'other_unset') {
            // Kartu "Other / not set": semua yang BUKAN Mandays atau Monthly, termasuk yang belum punya skema sama sekali.
            $known = array_merge(ExternalRules::schemeAliases('mandays'), ExternalRules::schemeAliases('monthly'));
            $q->where(fn ($s) => $s->whereNull('g.engagement_scheme')->orWhere('g.engagement_scheme', '')
                ->orWhereRaw("LOWER(REPLACE(REPLACE(REPLACE(g.engagement_scheme,' ',''),'-',''),'_','')) NOT IN (" . implode(',', array_fill(0, count($known), '?')) . ')', $known));

            return;
        }
        if ($scheme === 'other') {
            $known = array_merge(ExternalRules::schemeAliases('mandays'), ExternalRules::schemeAliases('monthly'));
            $q->whereNotNull('g.engagement_scheme')->where('g.engagement_scheme', '!=', '')
                ->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(g.engagement_scheme,' ',''),'-',''),'_','')) NOT IN (" . implode(',', array_fill(0, count($known), '?')) . ')', $known);

            return;
        }
        if (in_array($scheme, ['mandays', 'monthly', 'fixed_price', 'hourly'], true)) {
            $aliases = ExternalRules::schemeAliases($scheme);
            $q->whereRaw("LOWER(REPLACE(REPLACE(REPLACE(g.engagement_scheme,' ',''),'-',''),'_','')) IN (" . implode(',', array_fill(0, count($aliases), '?')) . ')', $aliases);
        }
    }

    /** @param  int[]  $ids  @return array<int,object{email:?string,phone:?string}> */
    private function contacts(array $ids): array
    {
        if (!$ids) {
            return [];
        }

        return DB::table('employee_address')->whereIn('employee_id', $ids)->orderBy('address_id')
            ->get(['employee_id', 'email_work', 'email_personal', 'cell_phone', 'telephone'])
            ->groupBy('employee_id')
            ->map(fn ($rows) => (object) [
                'email' => $rows->pluck('email_work')->merge($rows->pluck('email_personal'))->first(fn ($v) => trim((string) $v) !== ''),
                'phone' => $rows->pluck('cell_phone')->merge($rows->pluck('telephone'))->first(fn ($v) => trim((string) $v) !== ''),
            ])->all();
    }

    private function fullName(object $row): string
    {
        return trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ($row->nick_name ?: $row->eci);
    }

    private function decorate(object $row, string $today, ?object $contact): object
    {
        $row->name = $this->fullName($row);
        $row->initials = Initials::make($row->name);
        $row->scheme_key = ExternalRules::schemeKey($row->engagement_scheme);
        $row->scheme_label = $row->scheme_key !== null ? ExternalRules::schemeLabel($row->scheme_key) : null;
        $row->period_state = ExternalRules::periodState($row->engagement_start, $row->engagement_end, $today);
        $row->email = $contact->email ?? null;
        $row->phone = $contact->phone ?? null;
        if (property_exists($row, 'rate')) {
            $row->rate_text = $row->rate !== null ? ExternalRules::money($row->rate, $row->currency) : null;
            $row->rate_unit = ExternalRules::rateUnit($row->scheme_key);
        }

        return $row;
    }
}
