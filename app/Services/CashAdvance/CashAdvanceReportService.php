<?php

namespace App\Services\CashAdvance;

use App\Models\Attendance\Branch;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceApprovalStep;
use App\Models\CashAdvance\CashAdvanceReport;
use App\Models\CashAdvance\CashAdvanceReportApproval;
use App\Models\CashAdvance\CashAdvanceReportItem;
use App\Models\CashAdvance\CashAdvanceSetting;
use App\Models\DeliveryProject;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Notification;
use App\Models\ReportingPeriod;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin dokumen Cash Advance Report — pertanggungjawaban atas sebuah CA.
 *
 * Bentuknya kembar dengan CashAdvanceService (agnostik transport, gerbang
 * ['allowed','reason']), dengan SATU tanggung jawab tambahan yang menjadi
 * alasan seluruh sub-modul ini ada:
 *
 * 🔴 MENUTUP BUKU CA INDUKNYA. Menyetujui langkah terakhir sebuah CAR ikut
 * memperbarui `settlement_status`, `reported_amount`, `outstanding_amount`,
 * `settled_at`, dan `car_count` pada `cash_advances` — dalam TRANSAKSI YANG
 * SAMA. Menyetujui laporan lalu gagal menandai uang mukanya selesai adalah
 * keadaan setengah jadi yang tidak boleh bisa terjadi.
 */
class CashAdvanceReportService
{
    public const FLAG_OVER_ADVANCE  = 'over_advance';
    public const FLAG_LOCKED_PERIOD = 'locked_period';
    public const FLAG_ADMIN_CREATED = 'admin_created';
    public const FLAG_LATE_REPORT   = 'late_report';

    public function __construct(private CashAdvanceAmountService $amounts)
    {
    }

    // =======================================================================
    // PENULISAN
    // =======================================================================

    /**
     * Buat laporan pertanggungjawaban atas sebuah CA.
     *
     * @param  array  $payload  Termasuk `items` — peta berkunci ACAK, bukan indeks
     * @return array{allowed: bool, reason: string, report: CashAdvanceReport|null}
     */
    public function submit(CashAdvance $advance, int $employeeId, array $payload, ?int $createdBy = null): array
    {
        $settings = CashAdvanceSetting::current();
        $byAdmin  = $createdBy !== null && $createdBy !== $employeeId;

        $eligibility = $this->checkEligibility($advance, $settings);
        if (! $eligibility['allowed']) {
            return $eligibility + ['report' => null];
        }

        $date     = Carbon::parse($payload['report_date']);
        $dateGate = $this->checkDateRules($date, $settings, $byAdmin);
        if (! $dateGate['allowed']) {
            return $dateGate + ['report' => null];
        }

        $items = $this->prepareItems($payload['items'] ?? [], $settings);
        if (! $items['allowed']) {
            return ['allowed' => false, 'reason' => $items['reason'], 'report' => null];
        }

        $reported = round(array_sum(array_column($items['rows'], 'amount')), 2);

        // 🔴 advance_amount DIBEKUKAN di sini, sekali, dan tidak pernah dibaca
        // ulang dari relasi (Keputusan D139). Setelan
        // `allow_approver_adjust_amount` membuka jalan bagi penyetuju mengubah
        // nominal CA; tanpa pembekuan ini, selisih pada laporan yang SUDAH
        // ditandatangani akan berubah sendiri.
        $advanceAmount = (float) $advance->amount;

        $overGate = $this->amounts->checkReportedAgainstAdvance($reported, $advanceAmount, $settings);
        if (! $overGate['allowed']) {
            return $overGate + ['report' => null];
        }

        $steps = $this->activeSteps();
        if ($steps->isEmpty()) {
            return [
                'allowed' => false,
                'reason'  => 'No approval step is configured for cash advance reports. Ask an administrator to set one up in Cash Advance Settings.',
                'report'  => null,
            ];
        }

        $chosen = $this->resolveChosenApprovers($steps, $payload);
        if (! $chosen['allowed']) {
            return ['allowed' => false, 'reason' => $chosen['reason'], 'report' => null];
        }

        $report = DB::transaction(function () use (
            $advance, $employeeId, $createdBy, $byAdmin, $payload, $date,
            $advanceAmount, $reported, $items, $steps, $chosen, $settings
        ) {
            $settlement = $this->amounts->settlement($advanceAmount, [$reported]);

            $flags = [];

            if ($byAdmin) {
                $flags[self::FLAG_ADMIN_CREATED] = $createdBy;
            }

            if ($settlement['type'] === CashAdvanceReport::SETTLEMENT_CLAIM) {
                $flags[self::FLAG_OVER_ADVANCE] = [
                    'advance'  => $advanceAmount,
                    'reported' => $reported,
                ];
            }

            if ($this->periodLocked($date)) {
                $flags[self::FLAG_LOCKED_PERIOD] = true;
            }

            // Penanda terlambat dihitung SAAT DOKUMEN DIBUAT, dari selisih hari
            // sejak CA disetujui. Batas yang disebut apa adanya (jawaban C12):
            // tidak ada perintah terjadwal, jadi tidak ada notifikasi otomatis
            // tepat pada hari tenggat.
            if ($advance->completed_at) {
                // 🔴 `(int)` eksplisit. Carbon 3 mengembalikan FLOAT dari
                // diffInDays() — termasuk pecahan jam — dan menyerahkannya ke
                // parameter ber-tipe int memicu deprecation "Implicit conversion
                // from float ... loses precision". Membulatkan ke bawah memang
                // yang dimaksud di sini: keterlambatan dihitung dalam hari penuh,
                // bukan jam.
                $due = $this->amounts->reportDueStatus(
                    (int) floor($advance->completed_at->diffInDays($date, false)),
                    $settings
                );

                if ($due['overdue']) {
                    $flags[self::FLAG_LATE_REPORT] = ['days_late' => $due['days_late']];
                }
            }

            $report = CashAdvanceReport::create([
                'report_no'         => $this->nextReportNo($date),
                'cash_advance_id'   => $advance->id,
                'employee_id'       => $employeeId,
                'created_by'        => $createdBy,
                'report_date'       => $date->toDateString(),
                // 🔴 `?? null` lebih dulu, BARU `?:` — field opsional memang boleh
                // tidak dikirim sama sekali, dan `?:` sendirian memunculkan
                // warning "Undefined array key" yang mengotori log (D75).
                'description'       => trim((string) ($payload['description'] ?? '')),
                // Mata uang DISALIN dari CA, tidak boleh berbeda: tidak ada
                // tabel kurs di basis data ini, jadi selisih antar mata uang
                // tidak punya arti (jawaban C5).
                'currency'          => $advance->currency,
                'advance_amount'    => $advanceAmount,
                'reported_amount'   => $reported,
                'difference_amount' => $settlement['outstanding'],
                'settlement_type'   => $settlement['type'],
                'item_count'        => count($items['rows']),
                'notes'             => ($payload['notes'] ?? null) ?: null,
                'status'            => CashAdvanceReport::STATUS_SUBMITTED,
                'current_step_order'=> $steps->first()->order_seq,
                'period_year'       => (int) $date->format('Y'),
                'period_month'      => (int) $date->format('n'),
                'flags'             => $flags !== [] ? $flags : null,
            ]);

            $this->writeItems($report, $items['rows']);
            $this->snapshotSteps($report, $steps, $chosen['chosen']);

            // CA-nya kini berstatus "sedang dilaporkan". Belum `settled` —
            // laporannya masih menunggu persetujuan, dan angkanya masih bisa
            // berubah.
            $this->syncCashAdvance($advance);

            return $report;
        });

        return ['allowed' => true, 'reason' => '', 'report' => $report];
    }

    /**
     * Ubah laporan yang masih terbuka.
     *
     * 🔴 `advance_amount` TIDAK ikut diperbarui — ia dibekukan saat dokumen
     * dibuat dan tetap begitu seumur dokumen (D139).
     *
     * @return array{allowed: bool, reason: string}
     */
    public function update(CashAdvanceReport $report, array $payload, int $actorId): array
    {
        if (! $report->isOpen()) {
            return [
                'allowed' => false,
                'reason'  => 'This report is already ' . $report->status . ' and can no longer be edited.',
            ];
        }

        $settings = CashAdvanceSetting::current();
        $date     = Carbon::parse($payload['report_date']);

        $dateGate = $this->checkDateRules($date, $settings, true);
        if (! $dateGate['allowed']) {
            return $dateGate;
        }

        $items = $this->prepareItems($payload['items'] ?? [], $settings);
        if (! $items['allowed']) {
            return ['allowed' => false, 'reason' => $items['reason']];
        }

        $reported = round(array_sum(array_column($items['rows'], 'amount')), 2);

        $overGate = $this->amounts->checkReportedAgainstAdvance(
            $reported,
            (float) $report->advance_amount,
            $settings
        );
        if (! $overGate['allowed']) {
            return $overGate;
        }

        DB::transaction(function () use ($report, $payload, $date, $items) {
            $report->update([
                'report_date'  => $date->toDateString(),
                'description'  => trim((string) ($payload['description'] ?? '')),
                'notes'        => ($payload['notes'] ?? null) ?: null,
                'period_year'  => (int) $date->format('Y'),
                'period_month' => (int) $date->format('n'),
            ]);

            // Baris lama dibuang lalu ditulis ulang. Mencocokkan baris satu per
            // satu terdengar lebih hemat, tetapi kunci form-nya ACAK dan tidak
            // stabil antar-kiriman — jadi tidak ada yang bisa dicocokkan.
            $report->items()->delete();
            $this->writeItems($report, $items['rows']);

            $this->recalculateTotals($report);
            $this->syncCashAdvance($report->cashAdvance);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Batalkan laporan sendiri — hanya saat `submitted`.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function cancel(CashAdvanceReport $report, int $actorId): array
    {
        $settings = CashAdvanceSetting::current();

        if (! $settings->allow_requester_cancel) {
            return [
                'allowed' => false,
                'reason'  => 'Cancelling your own report is disabled in Cash Advance Settings.',
            ];
        }

        if ((int) $report->employee_id !== $actorId) {
            return ['allowed' => false, 'reason' => 'You can only cancel your own report.'];
        }

        if ($report->status !== CashAdvanceReport::STATUS_SUBMITTED) {
            return [
                'allowed' => false,
                'reason'  => 'This report is already ' . $report->status . ' and can no longer be cancelled.',
            ];
        }

        DB::transaction(function () use ($report, $actorId) {
            $report->approvals()
                   ->where('status', CashAdvanceReportApproval::STATUS_WAITING)
                   ->update(['status' => CashAdvanceReportApproval::STATUS_SKIPPED]);

            $report->update([
                'status'             => CashAdvanceReport::STATUS_CANCELLED,
                'current_step_order' => null,
                'cancelled_at'       => now(),
                'cancelled_by'       => $actorId,
            ]);

            // CA-nya kembali ke `unreported` bila ini satu-satunya laporannya —
            // uang mukanya sekali lagi menunggu pertanggungjawaban.
            $this->syncCashAdvance($report->cashAdvance);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Hapus laporan — SOFT DELETE beralasan (Keputusan D109).
     *
     * @return array{allowed: bool, reason: string}
     */
    public function softDelete(CashAdvanceReport $report, int $actorId, string $reason): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            return [
                'allowed' => false,
                'reason'  => 'A deletion reason of at least 5 characters is required for financial documents.',
            ];
        }

        DB::transaction(function () use ($report, $actorId, $reason) {
            $report->update([
                'deleted_by'    => $actorId,
                'delete_reason' => $reason,
            ]);

            $report->delete();

            // Buku CA dibuka kembali: laporannya sudah tidak terhitung.
            $this->syncCashAdvance($report->cashAdvance);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PERSETUJUAN
    // =======================================================================

    /**
     * @return array{allowed: bool, reason: string, approval: CashAdvanceReportApproval|null}
     */
    public function canAct(CashAdvanceReport $report, int $actorId, bool $canManage = false): array
    {
        if (! $report->isOpen()) {
            return [
                'allowed'  => false,
                'reason'   => 'This report is already ' . $report->status . '.',
                'approval' => null,
            ];
        }

        $approval = $report->currentApproval();

        if (! $approval || ! $approval->isWaiting()) {
            return [
                'allowed'  => false,
                'reason'   => 'There is no approval step waiting on this report.',
                'approval' => null,
            ];
        }

        $settings = CashAdvanceSetting::current();

        if ($this->periodLocked($report->report_date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === CashAdvanceSetting::LOCK_BLOCK_ALL) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this report is locked, and the current policy blocks everyone.',
                    'approval' => null,
                ];
            }

            if ($policy === CashAdvanceSetting::LOCK_BLOCK_EMPLOYEE && ! $canManage) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this report is locked. Only holders of the manage permission can act on it.',
                    'approval' => null,
                ];
            }
        }

        if (! $approval->allows($actorId, $this->roleIdsOf($actorId))) {
            return [
                'allowed'  => false,
                'reason'   => 'This report is waiting for ' . $approval->timelineLabel() . ', which you are not an approver for.',
                'approval' => null,
            ];
        }

        if ($actorId === (int) $report->employee_id && ! $settings->allow_self_approval) {
            $fallback = $settings->self_approval_fallback_role_id
                ? EmployeeRole::find($settings->self_approval_fallback_role_id)?->name
                : null;

            return [
                'allowed'  => false,
                'reason'   => 'You cannot approve your own cash advance report. Self-approval is disabled in Cash Advance Settings.'
                            . ($fallback ? ' Ask a holder of the "' . $fallback . '" role to review it.' : ''),
                'approval' => null,
            ];
        }

        return ['allowed' => true, 'reason' => '', 'approval' => $approval];
    }

    /**
     * Setujui langkah yang sedang menunggu.
     *
     * 🔴 Langkah TERAKHIR ikut menutup buku CA induknya, dalam transaksi yang
     * SAMA. Itulah satu-satunya perbedaan perilaku dengan CashAdvanceService.
     *
     * @return array{allowed: bool, reason: string, completed: bool}
     */
    public function approve(CashAdvanceReport $report, int $actorId, array $payload = [], bool $canManage = false): array
    {
        $gate = $this->canAct($report, $actorId, $canManage);

        if (! $gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason'], 'completed' => false];
        }

        /** @var CashAdvanceReportApproval $approval */
        $approval = $gate['approval'];

        $completed = DB::transaction(function () use ($report, $approval, $actorId, $payload) {
            $approval->update([
                'status'   => CashAdvanceReportApproval::STATUS_APPROVED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $payload['notes'] ?? null,
            ]);

            $next = $report->approvals()
                           ->where('order_seq', '>', $approval->order_seq)
                           ->where('status', CashAdvanceReportApproval::STATUS_WAITING)
                           ->orderBy('order_seq')
                           ->first();

            if ($next) {
                $report->update([
                    'status'             => CashAdvanceReport::STATUS_IN_REVIEW,
                    'current_step_order' => $next->order_seq,
                ]);

                $this->syncCashAdvance($report->cashAdvance);

                return false;
            }

            $report->update([
                'status'             => CashAdvanceReport::STATUS_APPROVED,
                'current_step_order' => null,
                'completed_at'       => now(),
            ]);

            // 🔴 DI SINILAH BUKUNYA DITUTUP — transaksi yang sama.
            $this->syncCashAdvance($report->cashAdvance);

            return true;
        });

        $this->notify($report, $completed ? 'approved' : 'progressed', $payload['notes'] ?? null);

        return ['allowed' => true, 'reason' => '', 'completed' => $completed];
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    public function reject(CashAdvanceReport $report, int $actorId, string $notes, bool $canManage = false): array
    {
        $notes = trim($notes);

        if (mb_strlen($notes) < 5) {
            return ['allowed' => false, 'reason' => 'A rejection reason of at least 5 characters is required.'];
        }

        $gate = $this->canAct($report, $actorId, $canManage);

        if (! $gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason']];
        }

        /** @var CashAdvanceReportApproval $approval */
        $approval = $gate['approval'];

        DB::transaction(function () use ($report, $approval, $actorId, $notes) {
            $approval->update([
                'status'   => CashAdvanceReportApproval::STATUS_REJECTED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $notes,
            ]);

            $report->approvals()
                   ->where('order_seq', '>', $approval->order_seq)
                   ->where('status', CashAdvanceReportApproval::STATUS_WAITING)
                   ->update(['status' => CashAdvanceReportApproval::STATUS_SKIPPED]);

            $report->update([
                'status'             => CashAdvanceReport::STATUS_REJECTED,
                'current_step_order' => null,
                'completed_at'       => now(),
            ]);

            // CA-nya kembali menunggu pertanggungjawaban: laporan yang ditolak
            // tidak menutup buku apa pun.
            $this->syncCashAdvance($report->cashAdvance);
        });

        $this->notify($report, 'rejected', $notes);

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PENYELESAIAN CA — inti sub-modul ini
    // =======================================================================

    /**
     * Menutup (atau membuka kembali) buku sebuah Cash Advance.
     *
     * 🔴 SYARAT MENGIKAT KEPUTUSAN D143 — baca sebelum menyentuh method ini.
     *
     * Angkanya dihitung dari JUMLAH SELURUH CAR yang `approved`, bukan dari satu
     * baris CAR terakhir. Setelan `car_multiple_per_ca` hari ini bawaannya mati
     * (satu CAR per CA), sehingga hasilnya identik dengan "ambil CAR-nya" — dan
     * justru itu yang membuat kesalahan ini mudah lolos.
     *
     * Kalau ditulis mengambil satu baris, menyalakan sakelar itu kelak akan
     * diam-diam menghasilkan SISA UANG YANG SALAH. Salahnya tidak muncul sebagai
     * galat; ia muncul sebagai angka keliru di dokumen keuangan, dan itulah
     * jenis kesalahan yang paling lama tidak ketahuan.
     *
     * Method ini dipanggil dari SETIAP jalur yang dapat mengubah keadaan CAR:
     * submit, update, cancel, softDelete, approve, dan reject. Semuanya di dalam
     * transaksi pemanggilnya.
     */
    public function syncCashAdvance(?CashAdvance $advance): void
    {
        if (! $advance) {
            return;
        }

        // Soft delete otomatis tersaring: relasinya memakai model ber-SoftDeletes.
        $reports = $advance->reports()->get(['status', 'reported_amount']);

        $approved = $reports->where('status', CashAdvanceReport::STATUS_APPROVED);

        // Laporan yang dibatalkan atau ditolak TIDAK dihitung sebagai laporan
        // yang menempel — kalau ikut dihitung, satu CAR yang ditolak akan
        // menahan CA-nya selamanya di keadaan `reporting`.
        $attached = $reports->whereIn('status', [
            CashAdvanceReport::STATUS_SUBMITTED,
            CashAdvanceReport::STATUS_IN_REVIEW,
            CashAdvanceReport::STATUS_APPROVED,
        ]);

        $settlement = $this->amounts->settlement(
            (float) $advance->amount,
            $approved->pluck('reported_amount')->map(fn ($v) => (float) $v)->all()
        );

        $status = $this->amounts->settlementStatus($attached->count(), $approved->count());

        $advance->update([
            'car_count'          => $attached->count(),
            'reported_amount'    => $approved->isEmpty() ? null : $settlement['reported'],
            'outstanding_amount' => $approved->isEmpty() ? null : $settlement['outstanding'],
            'settlement_status'  => $status,
            'settled_at'         => $status === CashAdvance::SETTLE_SETTLED ? now() : null,
        ]);
    }

    /**
     * Hitung ulang tiga angka laporan dari baris itemnya.
     *
     * 🔴 SATU-SATUNYA jalur tulis untuk `reported_amount`, `difference_amount`,
     * dan `settlement_type`. Menghitungnya saat dibaca terdengar lebih aman
     * sampai seseorang mengedit item dokumen yang sudah disetujui — yang
     * tercetak harus angka yang DISETUJUI, bukan angka yang kebetulan berlaku
     * saat halaman dibuka (alasan sama dengan D104).
     */
    public function recalculateTotals(CashAdvanceReport $report): void
    {
        $reported = round((float) $report->items()->sum('amount'), 2);

        $settlement = $this->amounts->settlement((float) $report->advance_amount, [$reported]);

        $report->update([
            'reported_amount'   => $reported,
            'difference_amount' => $settlement['outstanding'],
            'settlement_type'   => $settlement['type'],
            'item_count'        => $report->items()->count(),
        ]);
    }

    // =======================================================================
    // PEMBACAAN
    // =======================================================================

    public function history(int $employeeId, int $limit = 50): Collection
    {
        return CashAdvanceReport::with(['cashAdvance', 'approvals'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /** @return array<int> */
    public function pendingIdsFor(int $actorId): array
    {
        $roleIds = $this->roleIdsOf($actorId);

        return CashAdvanceReport::open()
            ->with('approvals')
            ->get()
            ->filter(function (CashAdvanceReport $report) use ($actorId, $roleIds) {
                $approval = $report->currentApproval();

                return $approval
                    && $approval->isWaiting()
                    && $approval->allows($actorId, $roleIds);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Langkah alur CAR yang aktif.
     *
     * 🔴 forModule() dengan MODULE_CAR — bukan MODULE_CA. Satu tabel melayani
     * dua alur (D136), dan tertukar di sini berarti laporan menunggu penyetuju
     * uang muka, bukan penyetuju laporan.
     */
    public function activeSteps(): Collection
    {
        return CashAdvanceApprovalStep::query()
            ->forModule(CashAdvanceApprovalStep::MODULE_CAR)
            ->active()
            ->ordered()
            ->get();
    }

    public function periodLocked($date): bool
    {
        $date   = $date instanceof Carbon ? $date : Carbon::parse($date);
        $coords = ReportingPeriod::periodFor($date);

        return ReportingPeriod::isClosed($coords['year'], $coords['month']);
    }

    /**
     * Kolom tanda tangan cetakan CAR — bentuk yang sama dengan CA.
     *
     * @return array<int, array{title: string, name: string, pending: bool}>
     */
    public function signatureColumns(CashAdvanceReport $report): array
    {
        $settings = CashAdvanceSetting::current();

        $columns = [[
            'title'   => 'Requester',
            'name'    => $this->nameOf($report->employee),
            'pending' => false,
        ], [
            'title'   => 'Accounting',
            'name'    => $this->nameOf($settings->accountingSigner),
            'pending' => false,
        ], [
            'title'   => 'Cashier',
            'name'    => $this->nameOf($settings->cashierSigner),
            'pending' => false,
        ]];

        $approverSteps = $report->approvals
            ->where('actor_role', CashAdvanceApprovalStep::ACTOR_APPROVER)
            ->where('status', '!=', CashAdvanceReportApproval::STATUS_SKIPPED);

        $acted = $approverSteps->where('status', CashAdvanceReportApproval::STATUS_APPROVED)
                               ->sortByDesc('order_seq')
                               ->first();

        if ($acted) {
            $columns[] = [
                'title'   => 'Approved by',
                'name'    => $this->nameOf($acted->actor),
                'pending' => false,
            ];

            return $columns;
        }

        $waiting = $approverSteps->sortBy('order_seq')->first();
        $name    = '';

        if ($waiting) {
            $ids  = array_map('intval', $waiting->approver_employee_ids ?? []);
            $name = count($ids) === 1
                ? $this->nameOf(Employee::with('basicData')->find($ids[0]))
                : '';
        }

        $columns[] = ['title' => 'Approved by', 'name' => $name, 'pending' => true];

        return $columns;
    }

    // =======================================================================
    // GERBANG & PEMBANTU
    // =======================================================================

    /**
     * Boleh membuat laporan atas CA ini? (jawaban C7 dan D143)
     *
     * @return array{allowed: bool, reason: string}
     */
    public function checkEligibility(CashAdvance $advance, ?CashAdvanceSetting $settings = null): array
    {
        $settings ??= CashAdvanceSetting::current();

        // 🔴 Berbeda dari aplikasi acuan, yang menyalakan tombol "Create CAR"
        // sejak dokumen masih `Submitted`. Mempertanggungjawabkan uang yang
        // belum tentu cair adalah dokumen tanpa arti — dan bila CA-nya kemudian
        // ditolak, laporannya jadi yatim.
        if (! $advance->isApproved()) {
            return [
                'allowed' => false,
                'reason'  => 'A cash advance report can only be created after the cash advance is approved.',
            ];
        }

        if ($advance->isSettled()) {
            return [
                'allowed' => false,
                'reason'  => 'This cash advance is already settled.',
            ];
        }

        if ($settings->car_multiple_per_ca) {
            return ['allowed' => true, 'reason' => ''];
        }

        // Sakelar mati = satu CAR per CA. Laporan yang dibatalkan atau ditolak
        // tidak dihitung — kalau ikut dihitung, satu kesalahan ketik akan
        // mengunci CA-nya selamanya.
        //
        // 🔴 Relasi yang SUDAH dimuat dipakai apa adanya. Halaman daftar
        // memanggil method ini sekali per baris; tanpa cabang ini, 25 baris
        // berarti 25 kueri tambahan.
        $openStatuses = [
            CashAdvanceReport::STATUS_SUBMITTED,
            CashAdvanceReport::STATUS_IN_REVIEW,
            CashAdvanceReport::STATUS_APPROVED,
        ];

        $existing = $advance->relationLoaded('reports')
            ? $advance->reports->whereIn('status', $openStatuses)->first()
            : $advance->reports()->whereIn('status', $openStatuses)->first();

        if ($existing) {
            return [
                'allowed' => false,
                'reason'  => 'This cash advance already has report ' . $existing->report_no
                             . '. Enable "Multiple reports per cash advance" in settings to add another.',
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Dari sekumpulan CA, mana yang BOLEH dibuatkan laporan sekarang.
     *
     * 🔴 LAHIR DARI CACAT NYATA yang ditemukan pemilik sistem (Keputusan D154).
     *
     * Halaman daftar dan panel "Waiting to be reported" semula memakai
     * `CashAdvance::acceptsReport()` dan scope `outstanding()`. Keduanya menjawab
     * pertanyaan yang BERBEDA — "apakah bukunya sudah ditutup?" — sehingga CA yang
     * SUDAH punya laporan berjalan tetap menampilkan tombol "Create CAR". Tombol
     * itu selalu berakhir 422, karena checkEligibility() memang menolaknya.
     *
     * Sekarang hanya ADA SATU tempat yang memutuskan: checkEligibility(). Method
     * ini cuma menerapkannya ke banyak baris sekaligus, memakai relasi yang sudah
     * dimuat supaya nol kueri tambahan.
     *
     * @param  iterable<CashAdvance>  $advances  sebaiknya sudah ->with(reports)
     * @return array<int>
     */
    public function eligibleAdvanceIds(iterable $advances): array
    {
        $settings = CashAdvanceSetting::current();
        $ids      = [];

        foreach ($advances as $advance) {
            if ($this->checkEligibility($advance, $settings)['allowed']) {
                $ids[] = (int) $advance->id;
            }
        }

        return $ids;
    }

    /**
     * @return array{allowed: bool, reason: string}
     */
    private function checkDateRules(Carbon $date, CashAdvanceSetting $settings, bool $byAdmin): array
    {
        $today = Carbon::today();

        if (! $settings->allow_future_date && $date->gt($today)) {
            return [
                'allowed' => false,
                'reason'  => 'Future dates are not allowed. Enable them in Cash Advance Settings if needed.',
            ];
        }

        if ($this->periodLocked($date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === CashAdvanceSetting::LOCK_BLOCK_ALL) {
                return [
                    'allowed' => false,
                    'reason'  => 'The reporting period for this date is locked, and the current policy blocks everyone.',
                ];
            }

            if ($policy === CashAdvanceSetting::LOCK_BLOCK_EMPLOYEE && ! $byAdmin) {
                return [
                    'allowed' => false,
                    'reason'  => 'The reporting period for this date is locked. Ask an administrator to submit it for you.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Menyiapkan baris realisasi: memvalidasi, menormalkan, membekukan label.
     *
     * 🔴 Masukannya PETA BERKUNCI ACAK (`items[<uuid>][amount]`), bukan indeks
     * berurutan. Baris ditambah dan dihapus lewat JavaScript; dengan indeks,
     * menghapus baris di tengah membuat sisanya bergeser. Server yang
     * mengurutkan ulang jadi `line_no`.
     *
     * @return array{allowed: bool, reason: string, rows: array<int, array<string, mixed>>}
     */
    private function prepareItems(array $rawItems, CashAdvanceSetting $settings): array
    {
        $rows    = [];
        $lineNo  = 1;
        $hosts   = $settings->allowedUrlHosts();

        foreach ($rawItems as $raw) {
            if (! is_array($raw)) {
                continue;
            }

            $description = trim((string) ($raw['description'] ?? ''));
            $amount      = $this->amounts->parseAmount($raw['amount'] ?? 0);

            // Baris kosong sepenuhnya dilewati tanpa keluhan: form selalu
            // menyisakan satu baris kosong di bawah, dan mengeluhkannya membuat
            // pengguna harus menghapus sesuatu yang tidak pernah ia isi.
            if ($description === '' && $amount <= 0) {
                continue;
            }

            if ($description === '') {
                return ['allowed' => false, 'reason' => 'Every expense line needs a description.', 'rows' => []];
            }

            if ($amount <= 0) {
                return [
                    'allowed' => false,
                    'reason'  => 'Line "' . $description . '" needs an amount greater than zero.',
                    'rows'    => [],
                ];
            }

            if (empty($raw['expense_date'])) {
                return [
                    'allowed' => false,
                    'reason'  => 'Line "' . $description . '" needs an expense date.',
                    'rows'    => [],
                ];
            }

            $receiptUrl = trim((string) ($raw['receipt_url'] ?? ''));

            if ($settings->car_require_receipt_url && $receiptUrl === '') {
                return [
                    'allowed' => false,
                    'reason'  => 'Line "' . $description . '" needs a receipt link.',
                    'rows'    => [],
                ];
            }

            if ($receiptUrl !== '' && $hosts !== []) {
                $host = strtolower((string) parse_url($receiptUrl, PHP_URL_HOST));
                $ok   = false;

                foreach ($hosts as $allowed) {
                    if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                        $ok = true;
                        break;
                    }
                }

                if (! $ok) {
                    return [
                        'allowed' => false,
                        'reason'  => 'The receipt link on line "' . $description . '" must point to one of: '
                                     . implode(', ', $hosts) . '.',
                        'rows'    => [],
                    ];
                }
            }

            $costCenter = $this->resolveItemCostCenter($raw, $settings);

            if (! $costCenter['allowed']) {
                return [
                    'allowed' => false,
                    'reason'  => 'Line "' . $description . '": ' . $costCenter['reason'],
                    'rows'    => [],
                ];
            }

            $rows[] = [
                'line_no'             => $lineNo++,
                'expense_date'        => Carbon::parse($raw['expense_date'])->toDateString(),
                'description'         => $description,
                'receipt_no'          => trim((string) ($raw['receipt_no'] ?? '')) ?: null,
                'amount'              => $amount,
                'receipt_url'         => $receiptUrl ?: null,
                'cost_center_type'    => $costCenter['type'],
                'branch_id'           => $costCenter['branch_id'],
                'delivery_project_id' => $costCenter['project_id'],
                'cost_center_label'   => $costCenter['label'],
            ];
        }

        if ($rows === []) {
            return ['allowed' => false, 'reason' => 'Add at least one expense line.', 'rows' => []];
        }

        return ['allowed' => true, 'reason' => '', 'rows' => $rows];
    }

    /**
     * 🔴 Satu baris hanya boleh mengisi SATU kolom pembebanan. Kolom yang lain
     * dipaksa NULL di server, bukan sekadar disembunyikan di layar — pengguna
     * yang berpindah Branch↔Project meninggalkan nilai lama di form (aturan
     * D127).
     *
     * @return array{allowed: bool, reason: string, type: ?string, branch_id: ?int, project_id: ?int, label: ?string}
     */
    private function resolveItemCostCenter(array $raw, CashAdvanceSetting $settings): array
    {
        $empty = [
            'allowed' => true, 'reason' => '',
            'type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null,
        ];

        $type = $raw['cost_center_type'] ?? null;

        if (! $type) {
            return $settings->require_cost_center
                ? ['allowed' => false, 'reason' => 'choose what it is charged to.',
                   'type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null]
                : $empty;
        }

        if (! $settings->allowsCostCenterType($type)) {
            return ['allowed' => false, 'reason' => 'that cost center type is not enabled.',
                    'type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null];
        }

        if ($type === CashAdvance::COST_CENTER_BRANCH) {
            $branch = Branch::where('is_active', 1)->find($raw['branch_id'] ?? null);

            if (! $branch) {
                return ['allowed' => false, 'reason' => 'choose a valid active branch.',
                        'type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null];
            }

            return [
                'allowed' => true, 'reason' => '',
                'type' => $type, 'branch_id' => (int) $branch->id, 'project_id' => null,
                'label' => trim(($branch->code ? $branch->code . ' – ' : '') . $branch->name),
            ];
        }

        $project = DeliveryProject::where('is_closed', 0)->find($raw['delivery_project_id'] ?? null);

        if (! $project) {
            return ['allowed' => false, 'reason' => 'choose a valid open project.',
                    'type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null];
        }

        return [
            'allowed' => true, 'reason' => '',
            'type' => $type, 'branch_id' => null, 'project_id' => (int) $project->id,
            'label' => trim(($project->io_number ? $project->io_number . ' – ' : '') . $project->name),
        ];
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function writeItems(CashAdvanceReport $report, array $rows): void
    {
        foreach ($rows as $row) {
            CashAdvanceReportItem::create($row + ['cash_advance_report_id' => $report->id]);
        }
    }

    /**
     * @return array{allowed: bool, reason: string, chosen: array<int,int>}
     */
    private function resolveChosenApprovers(Collection $steps, array $payload): array
    {
        $chosen = [];

        foreach ($steps as $step) {
            if (! $step->requester_selectable) {
                continue;
            }

            $candidates = $step->candidateEmployeeIds();

            if ($candidates === []) {
                return [
                    'allowed' => false,
                    'reason'  => 'Step "' . $step->name . '" has no approver to choose from. Ask an administrator to fix it in Cash Advance Settings.',
                    'chosen'  => [],
                ];
            }

            $picked = (int) ($payload['approver_ids'][$step->order_seq] ?? ($payload['approver_id'] ?? 0));

            if ($picked === 0 && count($candidates) === 1) {
                $picked = $candidates[0];
            }

            if (! in_array($picked, $candidates, true)) {
                return [
                    'allowed' => false,
                    'reason'  => 'Choose a valid approver for step "' . $step->name . '".',
                    'chosen'  => [],
                ];
            }

            $chosen[$step->order_seq] = $picked;
        }

        return ['allowed' => true, 'reason' => '', 'chosen' => $chosen];
    }

    /** @param array<int, int> $chosen */
    private function snapshotSteps(CashAdvanceReport $report, Collection $steps, array $chosen = []): void
    {
        foreach ($steps as $step) {
            $pick = $chosen[$step->order_seq] ?? null;

            CashAdvanceReportApproval::create([
                'cash_advance_report_id' => $report->id,
                'order_seq'              => $step->order_seq,
                'step_name'              => $step->name,
                'actor_role'             => $step->actor_role,
                'approver_type'          => $step->approver_type,
                'approver_role_id'       => $step->approver_role_id,
                'approver_employee_ids'  => $pick !== null ? [$pick] : $step->approver_employee_ids,
                'chosen_by_requester'    => $pick !== null,
                'status'                 => CashAdvanceReportApproval::STATUS_WAITING,
            ]);
        }
    }

    /**
     * Berapa laporan berjalan yang akan terkena bila satu langkah baru
     * ditambahkan pada urutan `$orderSeq`.
     */
    public function countOpenReportsBefore(int $orderSeq): int
    {
        return CashAdvanceReport::query()
            ->whereIn('status', CashAdvanceReport::OPEN_STATUSES)
            ->where('current_step_order', '<', $orderSeq)
            ->count();
    }

    /**
     * Terapkan satu langkah persetujuan BARU ke laporan yang sedang berjalan.
     *
     * Cermin CashAdvanceService::applyStepToOpenRequests(), aturan asimetris
     * Keputusan D116 berlaku sama: MENAMBAH boleh berlaku surut (memperketat),
     * MENGHAPUS tidak pernah.
     *
     * @return array{applied: int, request_nos: array<string>}
     */
    public function applyStepToOpenReports(CashAdvanceApprovalStep $step, ?int $actorId = null): array
    {
        $targets = CashAdvanceReport::query()
            ->whereIn('status', CashAdvanceReport::OPEN_STATUSES)
            ->where('current_step_order', '<', $step->order_seq)
            ->get();

        if ($targets->isEmpty()) {
            return ['applied' => 0, 'request_nos' => []];
        }

        $applied = [];

        DB::transaction(function () use ($targets, $step, &$applied) {
            foreach ($targets as $report) {
                if ($report->approvals()->where('order_seq', $step->order_seq)->exists()) {
                    continue;
                }

                CashAdvanceReportApproval::create([
                    'cash_advance_report_id' => $report->id,
                    'order_seq'              => $step->order_seq,
                    'step_name'              => $step->name,
                    'actor_role'             => $step->actor_role,
                    'approver_type'          => $step->approver_type,
                    'approver_role_id'       => $step->approver_role_id,
                    'approver_employee_ids'  => $step->approver_employee_ids,
                    'chosen_by_requester'    => false,
                    'status'                 => CashAdvanceReportApproval::STATUS_WAITING,
                ]);

                $report->addFlag('workflow_extended', $step->order_seq);
                $report->save();

                $applied[] = $report->report_no;
            }
        });

        if ($applied !== []) {
            Log::info('Cash advance report approval step applied to in-progress documents.', [
                'actor_id'    => $actorId,
                'step_name'   => $step->name,
                'order_seq'   => $step->order_seq,
                'applied'     => count($applied),
                'request_nos' => $applied,
            ]);
        }

        return ['applied' => count($applied), 'request_nos' => $applied];
    }

    /** Nomor dokumen berikutnya: CAR/2026/09/00001 (Keputusan D144). */
    private function nextReportNo(Carbon $date): string
    {
        $prefix = sprintf('CAR/%s/%s/', $date->format('Y'), $date->format('m'));

        $last = CashAdvanceReport::withTrashed()
            ->where('report_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('report_no')
            ->value('report_no');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /** @return array<int> */
    private function roleIdsOf(int $employeeId): array
    {
        return Employee::where('employee_id', $employeeId)->first()?->getRoleIds() ?? [];
    }

    private function nameOf(?Employee $employee): string
    {
        return $employee?->basicData?->nick_name ?? $employee?->eci ?? '—';
    }

    private function notify(CashAdvanceReport $report, string $outcome, ?string $notes = null): void
    {
        try {
            $amount = $this->amounts->format((float) $report->reported_amount);

            $message = match ($outcome) {
                'approved'   => "Your cash advance report {$report->report_no} ({$report->currency} {$amount}) was approved.",
                'rejected'   => "Your cash advance report {$report->report_no} was rejected.",
                'progressed' => "Your cash advance report {$report->report_no} passed a review step.",
                default      => "Your cash advance report {$report->report_no} was updated.",
            };

            Notification::create([
                'employee_id'      => $report->employee_id,
                'type'             => 'cash_advance_report_' . $outcome,
                'from_employee_id' => session('user.id'),
                'preview'          => $message . ($notes ? ' Note: ' . $notes : ''),
                'link'             => '/general/my-cash-advance-report',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send cash advance report notification.', [
                'report_id' => $report->id,
                'outcome'   => $outcome,
                'message'   => $e->getMessage(),
            ]);
        }
    }
}
