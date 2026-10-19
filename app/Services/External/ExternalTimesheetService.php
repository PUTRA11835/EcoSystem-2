<?php

namespace App\Services\External;

use App\Services\HolidayService;
use App\Support\Contracts\ContractRules;
use App\Support\External\ExternalBilling;
use App\Support\External\ExternalRules;
use App\Support\External\ExternalTimesheetRules as R;
use App\Support\Payroll\PayrollCalendar;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * External → Timesheet & Payment: lembar jam konsultan per bulan kontrak (buat, isi, ajukan, setujui/kembalikan) dan daftarnya.
 *
 * Satu lembar per (konsultan, kontrak EXTERNAL, bulan). Tarif dibaca dari ketentuan KONTRAK (`external_contract_terms`) dan DIBEKUKAN ke
 * lembar saat Submit, sehingga penagihan tidak berubah bila kontrak diubah kemudian. Tabel `timesheets` (internal) tidak disentuh.
 * Kegagalan yang bisa dibaca orang dilempar sebagai \DomainException (pesan Inggris, tampil apa adanya).
 */
class ExternalTimesheetService
{
    private const TYPE_ALIASES = ['EXTERNAL', 'EXT', 'PKS'];

    public function __construct(private ExternalWorkflowService $workflow) {}

    // ── Periode yang boleh diisi ──────────────────────────────────────────

    /**
     * Bulan-bulan yang boleh dibuatkan lembar: tiap bulan kontrak EXTERNAL (bukan Draft) sampai bulan ini, terbaru dulu.
     *
     * @return array<int,array<string,mixed>>
     */
    public function periodsFor(int $employeeId): array
    {
        $today = now();
        $contracts = DB::table('employee_contract')->where('employee_id', $employeeId)
            ->whereIn(DB::raw('UPPER(contract_type)'), self::TYPE_ALIASES)
            ->where(fn ($q) => $q->whereNull('lifecycle_status')->orWhere('lifecycle_status', '!=', 'draft'))
            ->whereNotNull('start_date')->get();
        $sheets = DB::table('external_timesheets')->where('employee_id', $employeeId)->get()
            ->keyBy(fn ($s) => $s->contract_id . '-' . $s->period_year . '-' . $s->period_month);

        $out = [];
        foreach ($contracts as $c) {
            $from = new \DateTimeImmutable(substr($c->start_date, 0, 7) . '-01');
            $until = new \DateTimeImmutable(($c->end_date && substr($c->end_date, 0, 10) < $today->toDateString() ? substr($c->end_date, 0, 7) : $today->format('Y-m')) . '-01');
            // Dari bulan terakhir mundur ke awal kontrak (maks 60 bulan terbaru), supaya bulan berjalan selalu tersedia.
            for ($d = $until, $n = 0; $d >= $from && $n < 60; $d = $d->modify('-1 month'), $n++) {
                $key = $c->contract_id . '-' . (int) $d->format('Y') . '-' . (int) $d->format('n');
                $sheet = $sheets->get($key);
                $out[] = [
                    'contract_id' => (int) $c->contract_id, 'contract_number' => $c->contract_number,
                    'year' => (int) $d->format('Y'), 'month' => (int) $d->format('n'), 'label' => $d->format('F Y'),
                    'sheet_id' => $sheet?->id, 'status' => $sheet?->status,
                ];
            }
        }
        usort($out, fn ($a, $b) => [$b['year'], $b['month']] <=> [$a['year'], $a['month']]);

        return $out;
    }

    /**
     * Lembar bulan itu; dibuat (Draft) bila belum ada dan bulan itu memang tercakup kontrak.
     *
     * @throws \DomainException
     */
    public function open(int $employeeId, int $contractId, int $year, int $month, int $actorId): int
    {
        $existing = DB::table('external_timesheets')->where(['employee_id' => $employeeId, 'contract_id' => $contractId, 'period_year' => $year, 'period_month' => $month])->value('id');
        if ($existing) {
            return (int) $existing;
        }
        $ok = collect($this->periodsFor($employeeId))->first(fn ($p) => $p['contract_id'] === $contractId && $p['year'] === $year && $p['month'] === $month);
        if (!$ok) {
            throw new \DomainException('That month is not covered by an external contract of this consultant.');
        }

        try {
            return DB::transaction(function () use ($employeeId, $contractId, $year, $month, $actorId) {
                return (int) DB::table('external_timesheets')->insertGetId([
                    'sheet_no' => $this->nextNumber($year, $month), 'employee_id' => $employeeId, 'contract_id' => $contractId,
                    'period_year' => $year, 'period_month' => $month, 'status' => R::DRAFT, 'created_by' => $actorId,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            });
        } catch (\Illuminate\Database\QueryException $e) {
            // Dua klik hampir bersamaan: lembar itu sudah dibuat oleh permintaan lain — buka saja.
            $id = DB::table('external_timesheets')->where(['employee_id' => $employeeId, 'contract_id' => $contractId, 'period_year' => $year, 'period_month' => $month])->value('id');
            if ($id) {
                return (int) $id;
            }
            throw $e;
        }
    }

    /** Nomor berikutnya per TAHUN lembar, dengan kunci baris; nomor tak pernah dipakai ulang. */
    private function nextNumber(int $year, int $month): string
    {
        DB::table('external_timesheet_sequences')->insertOrIgnore(['year' => $year, 'last_value' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $row = DB::table('external_timesheet_sequences')->where('year', $year)->lockForUpdate()->first();
        $next = (int) $row->last_value;
        do {
            $number = R::sheetNumber($year, $month, ++$next);
        } while (DB::table('external_timesheets')->where('sheet_no', $number)->exists());
        DB::table('external_timesheet_sequences')->where('id', $row->id)->update(['last_value' => $next, 'updated_at' => now()]);

        return $number;
    }

    // ── Baca ──────────────────────────────────────────────────────────────

    public function find(int $id): ?object
    {
        $sheet = DB::table('external_timesheets as t')
            ->join('employee as e', 'e.employee_id', '=', 't.employee_id')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 't.employee_id')
            ->join('employee_contract as c', 'c.contract_id', '=', 't.contract_id')
            ->where('t.id', $id)
            ->first(['t.*', 'e.eci', 'b.first_name', 'b.last_name', 'b.nick_name', 'b.position', 'c.contract_number', 'c.start_date as contract_start', 'c.end_date as contract_end']);
        if (!$sheet) {
            return null;
        }

        $sheet->name = trim(($sheet->first_name ?? '') . ' ' . ($sheet->last_name ?? '')) ?: ($sheet->nick_name ?: $sheet->eci);
        $sheet->period_label = date('F Y', strtotime(sprintf('%04d-%02d-01', $sheet->period_year, $sheet->period_month)));
        $sheet->editable = R::isEditable($sheet->status);
        $sheet->frozen = in_array($sheet->status, [R::SUBMITTED, R::APPROVED], true);
        $sheet->entries = DB::table('external_timesheet_entries')->where('timesheet_id', $id)->orderBy('work_date')->orderBy('start_time')->orderBy('id')->get();
        $sheet->live_hours = round((float) $sheet->entries->sum('hours'), 2);
        $sheet->pricing = $this->pricing($sheet);
        $sheet->invoice = DB::table('external_invoices')->where('timesheet_id', $id)->first();
        $sheet->actor_names = $this->workflow->names(array_filter([$sheet->submitted_by, $sheet->approved_by, $sheet->returned_by, $sheet->created_by,
            $sheet->invoice->verified_by ?? null, $sheet->invoice->rejected_by ?? null, $sheet->invoice->paid_by ?? null, $sheet->invoice->uploaded_by ?? null]));

        return $sheet;
    }

    /** Ketentuan kontrak saat ini (untuk lembar yang belum dibekukan). */
    public function liveTerms(int $contractId): ?object
    {
        return DB::table('external_contract_terms')->where('contract_id', $contractId)->first();
    }

    /**
     * Kuantitas dan bruto: dari snapshot lembar bila sudah diajukan, jika tidak dari ketentuan kontrak saat ini (perkiraan).
     *
     * @return array{scheme:?string,rate:?float,currency:string,unit:string,hpm:float,proration:string,milestone:float,hours:float,factor:float,qty:float,gross:?float,estimate:bool,missing_rate:bool}
     */
    public function pricing(object $sheet): array
    {
        $frozen = $sheet->rate !== null || in_array($sheet->status, [R::SUBMITTED, R::APPROVED], true);
        $live = $frozen ? null : $this->liveTerms((int) $sheet->contract_id);

        $scheme = $frozen ? $sheet->engagement_scheme : ($live->engagement_scheme ?? null);
        $rate = $frozen ? ($sheet->rate !== null ? (float) $sheet->rate : null) : (($live->rate ?? null) !== null ? (float) $live->rate : null);
        $hpm = (float) ($frozen ? $sheet->hours_per_manday : ($live->hours_per_manday ?? 8));
        $proration = $frozen ? $sheet->proration : ($live->proration ?? 'working_days');
        $milestone = (float) ($sheet->milestone_pct ?? 100);
        $hours = $frozen ? (float) $sheet->total_hours : (float) ($sheet->live_hours ?? 0);

        $factor = ExternalRules::schemeKey($scheme) === 'monthly' ? $this->monthFactor($sheet, $proration) : 1.0;
        $calc = ExternalBilling::compute(['scheme' => $scheme, 'rate' => $rate, 'hours_per_manday' => $hpm, 'milestone_pct' => $milestone], $hours, $factor);

        return [
            'scheme' => ExternalRules::schemeKey($scheme), 'rate' => $rate, 'currency' => $frozen ? $sheet->currency : ($live->currency ?? 'IDR'),
            'unit' => $frozen ? ($sheet->billing_unit ?: ExternalRules::defaultBillingUnit($scheme)) : ($live->billing_unit ?? ExternalRules::defaultBillingUnit($scheme)),
            'hpm' => $hpm, 'proration' => $proration, 'milestone' => $milestone, 'hours' => $hours, 'factor' => $factor,
            'qty' => $calc['qty'], 'gross' => $calc['gross'], 'estimate' => !$frozen, 'missing_rate' => $rate === null,
        ];
    }

    private function monthFactor(object $sheet, string $proration): float
    {
        [$first, $last] = R::periodBounds((int) $sheet->period_year, (int) $sheet->period_month);
        $holidays = $proration === 'working_days' ? (new HolidayService)->getHolidayDates((int) $sheet->period_year, (int) $sheet->period_year) : [];

        return ExternalBilling::monthFactor($proration, (string) $sheet->contract_start, $sheet->contract_end, (int) $sheet->period_year, (int) $sheet->period_month,
            $proration === 'working_days' ? PayrollCalendar::workdays($first, $last, 5, $holidays) : []);
    }

    // ── Isi lembar ────────────────────────────────────────────────────────

    /**
     * Simpan seluruh baris lembar (menggantikan yang lama). Hanya Draft/Returned. Tidak ada yang tersimpan bila ada baris yang salah.
     *
     * @param  array<int,mixed>  $raw
     * @throws \DomainException
     */
    public function save(int $id, array $raw, int $actorId, ?float $milestone = null): void
    {
        $sheet = $this->find($id);
        if (!$sheet) {
            throw new \DomainException('Timesheet not found.');
        }
        if (!$sheet->editable) {
            throw new \DomainException('A submitted or approved timesheet cannot be changed.');
        }

        [$first, $last] = R::periodBounds((int) $sheet->period_year, (int) $sheet->period_month);
        $days = (int) $this->workflow->settings()->backfill_days;
        $earliest = $days > 0 ? now()->subDays($days)->toDateString() : null;
        $keep = $sheet->entries->map(fn ($e) => substr((string) $e->work_date, 0, 10))->unique()->values()->all();
        $projects = DB::table('delivery_projects')->pluck('id')->map(fn ($p) => (int) $p)->all();

        [$clean, $errors] = R::normalizeEntries($raw, $first, $last, $sheet->contract_start, $sheet->contract_end, now()->toDateString(), $earliest, $keep, $projects);
        if ($errors) {
            throw new \DomainException(implode(' ', $errors));
        }

        $scheme = ExternalRules::schemeKey($this->liveTerms((int) $sheet->contract_id)->engagement_scheme ?? null);
        if ($milestone !== null && $scheme === 'fixed_price' && ($milestone <= 0 || $milestone > 100)) {
            throw new \DomainException('The share of the fixed price must be between 1 and 100 percent.');
        }

        DB::transaction(function () use ($id, $clean, $actorId, $milestone, $scheme) {
            // Dikunci dan dicek ulang: bila lembar baru saja diajukan oleh permintaan lain, baris tidak boleh ditimpa.
            $locked = DB::table('external_timesheets')->where('id', $id)->lockForUpdate()->first();
            if (!$locked || !R::isEditable($locked->status)) {
                throw new \DomainException('A submitted or approved timesheet cannot be changed.');
            }
            if ($milestone !== null && $scheme === 'fixed_price') {
                DB::table('external_timesheets')->where('id', $id)->update(['milestone_pct' => round($milestone, 2)]);
            }
            DB::table('external_timesheet_entries')->where('timesheet_id', $id)->delete();
            foreach ($clean as $row) {
                DB::table('external_timesheet_entries')->insert($row + ['timesheet_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
            }
            DB::table('external_timesheets')->where('id', $id)->update([
                'total_hours' => round(array_sum(array_column($clean, 'hours')), 2), 'updated_at' => now(),
            ]);
        });
    }

    // ── Alur status ───────────────────────────────────────────────────────

    /**
     * Ajukan: membekukan tarif dari kontrak, menghitung kuantitas dan bruto, membekukan penyetuju, dan memberi tahu mereka.
     *
     * @throws \DomainException
     */
    public function submit(int $id, int $actorId): void
    {
        $sheet = $this->find($id);
        if (!$sheet) {
            throw new \DomainException('Timesheet not found.');
        }
        if (!R::canTransition($sheet->status, R::SUBMITTED)) {
            throw new \DomainException('Only a Draft or Returned timesheet can be submitted.');
        }
        if ($sheet->entries->isEmpty() || $sheet->live_hours <= 0) {
            throw new \DomainException('Add at least one row of hours before submitting.');
        }
        $terms = $this->liveTerms((int) $sheet->contract_id);
        if (!$terms || $terms->rate === null) {
            throw new \DomainException('This contract has no rate yet. Ask HR to open External Contract, edit the contract and save its rate and billing terms.');
        }
        if (ExternalRules::schemeKey($terms->engagement_scheme) === null) {
            throw new \DomainException('This contract has no contract type (Mandays, Monthly, Fixed price or Hourly). Ask HR to edit the contract and save it.');
        }

        // Dibekukan lebih dulu agar pricing() membaca snapshot, lalu totalnya ditulis.
        $freeze = [
            'engagement_scheme' => $terms->engagement_scheme, 'rate' => $terms->rate, 'currency' => $terms->currency ?: 'IDR',
            'billing_unit' => $terms->billing_unit, 'hours_per_manday' => $terms->hours_per_manday, 'proration' => $terms->proration,
            'total_hours' => $sheet->live_hours,
        ];
        $frozen = (object) array_merge((array) $sheet, $freeze, ['status' => R::SUBMITTED]);
        $price = $this->pricing($frozen);
        if ($price['scheme'] === 'monthly' && $price['factor'] <= 0) {
            throw new \DomainException('The contract does not cover this month, so a monthly rate cannot be billed.');
        }

        DB::transaction(function () use ($id, $sheet, $freeze, $price, $actorId) {
            // Bersyarat pada status lama: dua pengajuan serentak tidak menimpa satu sama lain.
            $moved = DB::table('external_timesheets')->where('id', $id)->whereIn('status', [R::DRAFT, R::RETURNED])->update($freeze + [
                'billable_qty' => $price['qty'], 'gross_amount' => $price['gross'], 'status' => R::SUBMITTED,
                'submitted_at' => now(), 'submitted_by' => $actorId, 'updated_at' => now(),
            ]);
            if ($moved !== 1) {
                throw new \DomainException('This timesheet was already submitted.');
            }
            $this->workflow->snapshot($id, (int) $sheet->employee_id);
        });

        $this->workflow->notify($this->workflow->approversOf($id, R::STAGE_APPROVAL, (int) $sheet->employee_id), 'external_timesheet_pending_approval',
            "{$sheet->name} submitted the timesheet {$sheet->sheet_no} ({$sheet->period_label}) for approval.", "/general/external/timesheet/{$id}", $actorId, (int) $sheet->employee_id);
    }

    /** @throws \DomainException */
    public function approve(int $id, int $actorId): void
    {
        $sheet = $this->find($id);
        if (!$sheet || !R::canTransition($sheet->status, R::APPROVED)) {
            throw new \DomainException('Only a submitted timesheet can be approved.');
        }
        $moved = DB::table('external_timesheets')->where('id', $id)->where('status', R::SUBMITTED)->update(['status' => R::APPROVED, 'approved_at' => now(), 'approved_by' => $actorId, 'updated_at' => now()]);
        if ($moved !== 1) {
            throw new \DomainException('This timesheet was already handled by someone else.');
        }

        $this->workflow->notify([(int) $sheet->employee_id], 'external_timesheet_approved',
            "Your timesheet {$sheet->sheet_no} ({$sheet->period_label}) was approved. Upload your invoice in My Timesheet & Invoice.", "/general/external/my-timesheet?sheet={$id}", $actorId);
    }

    /** @throws \DomainException */
    public function returnSheet(int $id, int $actorId, string $reason): void
    {
        $reason = trim($reason);
        $sheet = $this->find($id);
        if (!$sheet || !R::canTransition($sheet->status, R::RETURNED)) {
            throw new \DomainException('Only a submitted timesheet can be returned.');
        }
        if ($reason === '') {
            throw new \DomainException('Give a reason so the consultant knows what to correct.');
        }
        $moved = DB::table('external_timesheets')->where('id', $id)->where('status', R::SUBMITTED)->update([
            'status' => R::RETURNED, 'returned_at' => now(), 'returned_by' => $actorId, 'return_reason' => mb_substr($reason, 0, 2000), 'updated_at' => now(),
        ]);
        if ($moved !== 1) {
            throw new \DomainException('This timesheet was already handled by someone else.');
        }

        $this->workflow->notify([(int) $sheet->employee_id], 'external_timesheet_returned',
            "Your timesheet {$sheet->sheet_no} ({$sheet->period_label}) was returned: {$reason}", "/general/external/my-timesheet?sheet={$id}", $actorId);
    }

    /** Hanya Draft yang belum pernah diajukan boleh dihapus. @throws \DomainException */
    public function delete(int $id): void
    {
        $sheet = DB::table('external_timesheets')->where('id', $id)->first();
        if (!$sheet) {
            return;
        }
        if ($sheet->status !== R::DRAFT || $sheet->submitted_at !== null) {
            throw new \DomainException('Only a Draft that was never submitted can be deleted.');
        }
        DB::table('external_timesheets')->where('id', $id)->delete();
    }

    // ── Daftar ────────────────────────────────────────────────────────────

    /**
     * @param  array{search?:string,status?:string,month?:string,invoice?:string}  $filters  month = "YYYY-MM"
     */
    public function listing(array $filters, int $perPage, bool $withAmounts, ?int $onlyEmployeeId = null, bool $invoiceView = false): LengthAwarePaginator
    {
        $q = DB::table('external_timesheets as t')
            ->join('employee as e', 'e.employee_id', '=', 't.employee_id')
            ->join('employee_basic_data as b', 'b.employee_id', '=', 't.employee_id')
            ->join('employee_contract as c', 'c.contract_id', '=', 't.contract_id')
            ->leftJoin('external_invoices as i', 'i.timesheet_id', '=', 't.id')
            ->when($onlyEmployeeId !== null, fn ($x) => $x->where('t.employee_id', $onlyEmployeeId))
            ->when($invoiceView, fn ($x) => $x->where(fn ($g) => $g->whereNotNull('i.id')->orWhere('t.status', R::APPROVED)))
            ->when(($filters['search'] ?? '') !== '', function ($x) use ($filters) {
                $like = '%' . str_replace(['%', '_'], ['\\%', '\\_'], $filters['search']) . '%';
                $x->where(fn ($s) => $s->whereRaw("CONCAT_WS(' ', b.first_name, b.last_name) like ?", [$like])
                    ->orWhere('e.eci', 'like', $like)->orWhere('t.sheet_no', 'like', $like)->orWhere('c.contract_number', 'like', $like)->orWhere('i.invoice_no', 'like', $like));
            })
            ->when(isset(R::STATUS_LABELS[$filters['status'] ?? '']), fn ($x) => $x->where('t.status', $filters['status']))
            ->when(isset(R::INVOICE_LABELS[$filters['invoice'] ?? '']), fn ($x) => $x->where('i.status', $filters['invoice']))
            ->when(preg_match('/^\d{4}-\d{2}$/', (string) ($filters['month'] ?? '')), fn ($x) => $x
                ->where('t.period_year', (int) substr($filters['month'], 0, 4))->where('t.period_month', (int) substr($filters['month'], 5, 2)))
            ->select(array_merge([
                't.id', 't.sheet_no', 't.employee_id', 't.contract_id', 't.period_year', 't.period_month', 't.status', 't.engagement_scheme', 't.billing_unit',
                't.total_hours', 't.billable_qty', 't.submitted_at', 't.approved_at',
                'e.eci', 'b.first_name', 'b.last_name', 'b.nick_name', 'c.contract_number',
                'i.id as invoice_id', 'i.invoice_no', 'i.status as invoice_status', 'i.due_date', 'i.paid_date',
            ], $withAmounts ? ['t.gross_amount', 't.currency', 't.rate', 'i.amount as invoice_amount'] : []))
            ->orderByDesc('t.period_year')->orderByDesc('t.period_month')->orderByDesc('t.id');

        $page = $q->paginate($perPage)->withQueryString();
        $page->getCollection()->transform(function ($row) {
            $row->name = trim(($row->first_name ?? '') . ' ' . ($row->last_name ?? '')) ?: ($row->nick_name ?: $row->eci);
            $row->period_label = date('F Y', strtotime(sprintf('%04d-%02d-01', $row->period_year, $row->period_month)));
            $row->scheme_key = ExternalRules::schemeKey($row->engagement_scheme);

            return $row;
        });

        return $page;
    }

    /** Hitungan kartu ringkasan. */
    public function summary(?int $onlyEmployeeId = null): array
    {
        $r = DB::table('external_timesheets as t')->leftJoin('external_invoices as i', 'i.timesheet_id', '=', 't.id')
            ->when($onlyEmployeeId !== null, fn ($x) => $x->where('t.employee_id', $onlyEmployeeId))
            ->selectRaw("COUNT(*) as total,
                SUM(t.status='draft') as draft, SUM(t.status='submitted') as submitted, SUM(t.status='returned') as returned, SUM(t.status='approved') as approved,
                SUM(t.status='approved' AND i.id IS NULL) as awaiting_invoice, SUM(i.status IN ('uploaded')) as to_verify,
                SUM(i.status='verified') as to_pay, SUM(i.status='paid') as paid")->first();

        return array_map('intval', (array) $r);
    }
}
