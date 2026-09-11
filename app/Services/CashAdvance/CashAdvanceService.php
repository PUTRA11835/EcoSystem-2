<?php

namespace App\Services\CashAdvance;

use App\Models\Attendance\Branch;
use App\Models\CashAdvance\CashAdvance;
use App\Models\CashAdvance\CashAdvanceApproval;
use App\Models\CashAdvance\CashAdvanceApprovalStep;
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
 * Mesin dokumen Cash Advance — submit, ubah, batal, hapus, setujui, tolak.
 *
 * AGNOSTIK TRANSPORT: tidak menyentuh Request maupun Response, dan tidak pernah
 * membaca session. Identitas pelaku SELALU dikirim pemanggil sebagai
 * `$actorId`. Itu yang membuat satu mesin ini melayani empat pintu masuk —
 * karyawan, admin atas nama karyawan, penyetuju, dan skrip uji — tanpa satu pun
 * cabang khusus.
 *
 * Gerbang bisnis selalu mengembalikan ['allowed' => bool, 'reason' => string],
 * meniru PeriodService. Pemanggilnya tidak perlu menebak apakah `false` berarti
 * "ditolak" atau "gagal memeriksa".
 *
 * 🔴 Aritmetika uangnya TIDAK ada di sini — seluruhnya di
 * CashAdvanceAmountService yang murni dan ber-unit-test. Berkas ini hanya
 * mengurus urutan kejadian dan penyimpanannya.
 */
class CashAdvanceService
{
    /** Sinyal anomali yang dapat menempel pada dokumen (pola D10). */
    public const FLAG_OVER_LIMIT     = 'over_limit';
    public const FLAG_SELF_APPROVED  = 'self_approved';
    public const FLAG_LOCKED_PERIOD  = 'locked_period';
    public const FLAG_CAR_OVERDUE    = 'car_overdue';
    public const FLAG_ADMIN_CREATED  = 'admin_created';

    public function __construct(private CashAdvanceAmountService $amounts)
    {
    }

    // =======================================================================
    // PENULISAN
    // =======================================================================

    /**
     * Buat pengajuan baru.
     *
     * @param  int       $employeeId  PEMILIK dokumen — pemohonnya
     * @param  int|null  $createdBy   Terisi hanya bila admin memakai "New CA"
     * @return array{allowed: bool, reason: string, request: CashAdvance|null}
     */
    public function submit(int $employeeId, array $payload, ?int $createdBy = null): array
    {
        $settings = CashAdvanceSetting::current();
        $byAdmin  = $createdBy !== null && $createdBy !== $employeeId;

        $date = Carbon::parse($payload['request_date']);

        $dateGate = $this->checkDateRules($date, $settings, $byAdmin);
        if (! $dateGate['allowed']) {
            return $dateGate + ['request' => null];
        }

        $rangeGate = $this->checkDateRange($date, $payload['request_date_to'] ?? null, $settings);
        if (! $rangeGate['allowed']) {
            return $rangeGate + ['request' => null];
        }

        $amount     = $this->amounts->parseAmount($payload['amount'] ?? 0);
        $limitGate  = $this->amounts->checkLimits($amount, $settings);
        if (! $limitGate['allowed']) {
            return ['allowed' => false, 'reason' => $limitGate['reason'], 'request' => null];
        }

        $outstandingGate = $this->checkOutstandingRule($employeeId, $settings);
        if (! $outstandingGate['allowed']) {
            return $outstandingGate + ['request' => null];
        }

        $costCenter = $this->resolveCostCenter($payload, $settings);
        if (! $costCenter['allowed']) {
            return ['allowed' => false, 'reason' => $costCenter['reason'], 'request' => null];
        }

        $urlGate = $this->checkDetailUrl($payload['detail_url'] ?? null, $settings);
        if (! $urlGate['allowed']) {
            return $urlGate + ['request' => null];
        }

        $steps = $this->activeSteps();
        if ($steps->isEmpty()) {
            return [
                'allowed' => false,
                'reason'  => 'No approval step is configured. Ask an administrator to set one up in Cash Advance Settings.',
                'request' => null,
            ];
        }

        $chosen = $this->resolveChosenApprovers($steps, $payload);
        if (! $chosen['allowed']) {
            return ['allowed' => false, 'reason' => $chosen['reason'], 'request' => null];
        }

        $request = DB::transaction(function () use (
            $employeeId, $createdBy, $byAdmin, $payload, $date, $amount,
            $settings, $limitGate, $costCenter, $steps, $chosen
        ) {
            $flags = $limitGate['flags'];

            if ($byAdmin) {
                $flags[self::FLAG_ADMIN_CREATED] = $createdBy;
            }

            if ($this->periodLocked($date)) {
                $flags[self::FLAG_LOCKED_PERIOD] = true;
            }

            $request = CashAdvance::create([
                'request_no'         => $this->nextRequestNo($date),
                'employee_id'        => $employeeId,
                'created_by'         => $createdBy,
                'request_date'       => $date->toDateString(),
                // 🔴 `?? null` lebih dulu, BARU `?:`. Field opsional memang boleh
                // tidak dikirim sama sekali — pemanggil dari skrip, API, atau
                // form yang fieldnya disembunyikan setelan. `?:` sendirian
                // memunculkan warning "Undefined array key" yang mengotori log
                // dan menyamarkan masalah sungguhan (D75).
                'request_date_to'    => ($payload['request_date_to'] ?? null) ?: null,
                'description'        => trim((string) ($payload['description'] ?? '')),
                'currency'           => $settings->defaultCurrency(),
                'amount'             => $amount,
                'detail_url'         => ($payload['detail_url'] ?? null) ?: null,
                'notes'              => ($payload['notes'] ?? null) ?: null,
                'cost_center_type'   => $costCenter['type'],
                'charged_branch_id'  => $costCenter['branch_id'],
                'charged_project_id' => $costCenter['project_id'],
                'charged_to_label'   => $costCenter['label'],
                'status'             => CashAdvance::STATUS_SUBMITTED,
                'current_step_order' => $steps->first()->order_seq,
                'settlement_status'  => CashAdvance::SETTLE_UNREPORTED,
                'car_count'          => 0,
                'period_year'        => (int) $date->format('Y'),
                'period_month'       => (int) $date->format('n'),
                'flags'              => $flags !== [] ? $flags : null,
            ]);

            $this->snapshotSteps($request, $steps, $chosen['chosen']);

            return $request;
        });

        return ['allowed' => true, 'reason' => '', 'request' => $request];
    }

    /**
     * Ubah dokumen yang masih terbuka.
     *
     * 🔴 Alur persetujuannya TIDAK disusun ulang. Mengubah nominal tidak boleh
     * memutar dokumen kembali ke langkah pertama — penyetuju yang sudah meninjau
     * tidak kehilangan pekerjaannya. Yang berubah hanya isinya, dan perubahan
     * itu tercatat di `flags`.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function update(CashAdvance $request, array $payload, int $actorId): array
    {
        if (! $request->isOpen()) {
            return [
                'allowed' => false,
                'reason'  => 'This document is already ' . $request->status . ' and can no longer be edited.',
            ];
        }

        $settings = CashAdvanceSetting::current();
        $date     = Carbon::parse($payload['request_date']);

        $dateGate = $this->checkDateRules($date, $settings, true);
        if (! $dateGate['allowed']) {
            return $dateGate;
        }

        $rangeGate = $this->checkDateRange($date, $payload['request_date_to'] ?? null, $settings);
        if (! $rangeGate['allowed']) {
            return $rangeGate;
        }

        $amount    = $this->amounts->parseAmount($payload['amount'] ?? 0);
        $limitGate = $this->amounts->checkLimits($amount, $settings);
        if (! $limitGate['allowed']) {
            return ['allowed' => false, 'reason' => $limitGate['reason']];
        }

        $costCenter = $this->resolveCostCenter($payload, $settings);
        if (! $costCenter['allowed']) {
            return ['allowed' => false, 'reason' => $costCenter['reason']];
        }

        $urlGate = $this->checkDetailUrl($payload['detail_url'] ?? null, $settings);
        if (! $urlGate['allowed']) {
            return $urlGate;
        }

        DB::transaction(function () use ($request, $payload, $date, $amount, $costCenter, $limitGate, $actorId) {
            $previousAmount = (float) $request->amount;

            $flags = $request->flags ?? [];

            // Sinyal batas nominal selalu dihitung ulang: nominal yang diturunkan
            // ke bawah batas harus MENGHILANGKAN penandanya, bukan meninggalkan
            // tanda yang tidak lagi benar.
            unset($flags[self::FLAG_OVER_LIMIT]);
            $flags = array_merge($flags, $limitGate['flags']);

            if (abs($previousAmount - $amount) >= 0.005) {
                $flags['amount_edited'] = [
                    'from' => $previousAmount,
                    'to'   => $amount,
                    'by'   => $actorId,
                    'at'   => now()->toDateTimeString(),
                ];
            }

            $request->update([
                'request_date'       => $date->toDateString(),
                'request_date_to'    => ($payload['request_date_to'] ?? null) ?: null,
                'description'        => trim((string) ($payload['description'] ?? '')),
                'amount'             => $amount,
                'detail_url'         => ($payload['detail_url'] ?? null) ?: null,
                'notes'              => ($payload['notes'] ?? null) ?: null,
                'cost_center_type'   => $costCenter['type'],
                'charged_branch_id'  => $costCenter['branch_id'],
                'charged_project_id' => $costCenter['project_id'],
                'charged_to_label'   => $costCenter['label'],
                'period_year'        => (int) $date->format('Y'),
                'period_month'       => (int) $date->format('n'),
                'flags'              => $flags !== [] ? $flags : null,
            ]);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Batalkan dokumen sendiri.
     *
     * 🔴 Hanya saat status `submitted`. Begitu satu penyetuju bertindak, status
     * berubah jadi `in_review` dan tombolnya hilang — penyetuju yang sudah
     * meluangkan waktu meninjau tidak boleh kehilangan pekerjaannya karena
     * pemohon berubah pikiran.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function cancel(CashAdvance $request, int $actorId): array
    {
        $settings = CashAdvanceSetting::current();

        if (! $settings->allow_requester_cancel) {
            return [
                'allowed' => false,
                'reason'  => 'Cancelling your own cash advance is disabled in Cash Advance Settings.',
            ];
        }

        if ((int) $request->employee_id !== $actorId) {
            return ['allowed' => false, 'reason' => 'You can only cancel your own cash advance.'];
        }

        if ($request->status !== CashAdvance::STATUS_SUBMITTED) {
            return [
                'allowed' => false,
                'reason'  => 'This document is already ' . $request->status . ' and can no longer be cancelled.',
            ];
        }

        DB::transaction(function () use ($request, $actorId) {
            // Langkah yang belum dijalani ditandai `skipped`, bukan dibiarkan
            // `waiting`. Akibat langsungnya terlihat di kertas: signatureColumns()
            // melewatinya, sehingga cetakan dokumen batal tidak menampilkan kolom
            // tanda tangan untuk orang yang tidak akan pernah bertindak.
            $request->approvals()
                    ->where('status', CashAdvanceApproval::STATUS_WAITING)
                    ->update(['status' => CashAdvanceApproval::STATUS_SKIPPED]);

            $request->update([
                'status'             => CashAdvance::STATUS_CANCELLED,
                'current_step_order' => null,
                'cancelled_at'       => now(),
                'cancelled_by'       => $actorId,
            ]);
        });

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Hapus dokumen — SOFT DELETE beralasan (Keputusan D109).
     *
     * Dokumen keuangan tidak boleh hilang tanpa jejak, dan alasannya WAJIB.
     *
     * 🔴 CA yang sudah punya CAR ditolak: menghapus uang mukanya akan
     * meninggalkan laporan pertanggungjawaban yang menunjuk dokumen yang tidak
     * lagi terlihat. Basis data pun menolaknya lewat `restrictOnDelete`, tetapi
     * pesan galat basis data bukan jawaban yang pantas dibaca pengguna.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function softDelete(CashAdvance $request, int $actorId, string $reason): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5) {
            return [
                'allowed' => false,
                'reason'  => 'A deletion reason of at least 5 characters is required for financial documents.',
            ];
        }

        if ($request->reports()->exists()) {
            return [
                'allowed' => false,
                'reason'  => 'This cash advance already has a settlement report and cannot be deleted. Delete the report first.',
            ];
        }

        DB::transaction(function () use ($request, $actorId, $reason) {
            $request->update([
                'deleted_by'    => $actorId,
                'delete_reason' => $reason,
            ]);

            $request->delete();
        });

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PERSETUJUAN
    // =======================================================================

    /**
     * Boleh bertindak pada langkah yang sedang menunggu?
     *
     * DUA LAPIS IZIN — yang ini lapis KEDUA. Lapis pertama (slug
     * `general.cash-advance.approve`) menentukan siapa boleh MEMBUKA halaman
     * peninjauan; yang di sini menentukan siapa boleh menyetujui dokumen YANG
     * MANA. Keduanya jangan disatukan.
     *
     * @return array{allowed: bool, reason: string, approval: CashAdvanceApproval|null}
     */
    public function canAct(CashAdvance $request, int $actorId, bool $canManage = false): array
    {
        if (! $request->isOpen()) {
            return [
                'allowed'  => false,
                'reason'   => 'This document is already ' . $request->status . '.',
                'approval' => null,
            ];
        }

        $approval = $request->currentApproval();

        if (! $approval || ! $approval->isWaiting()) {
            return [
                'allowed'  => false,
                'reason'   => 'There is no approval step waiting on this document.',
                'approval' => null,
            ];
        }

        $settings = CashAdvanceSetting::current();

        if ($this->periodLocked($request->request_date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === CashAdvanceSetting::LOCK_BLOCK_ALL) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this document is locked, and the current policy blocks everyone.',
                    'approval' => null,
                ];
            }

            if ($policy === CashAdvanceSetting::LOCK_BLOCK_EMPLOYEE && ! $canManage) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this document is locked. Only holders of the manage permission can act on it.',
                    'approval' => null,
                ];
            }
        }

        if (! $approval->allows($actorId, $this->roleIdsOf($actorId))) {
            return [
                'allowed'  => false,
                'reason'   => 'This document is waiting for ' . $approval->timelineLabel() . ', which you are not an approver for.',
                'approval' => null,
            ];
        }

        if ($actorId === (int) $request->employee_id && ! $settings->allow_self_approval) {
            // 🔴 `self_approval_fallback_role_id` DIBACA DI SINI, dan hanya di
            // sini. Tanpa ini setelannya tersimpan tetapi tidak pernah mengubah
            // apa pun — persis kegagalan D52, dan itulah keadaannya di modul
            // Reimbursement (ditemukan saat audit setelan P8). Yang dilakukannya
            // sederhana tetapi nyata: pesan penolakan MENYEBUT ke siapa dokumen
            // ini harus dibawa, alih-alih membiarkan pemohon menebak.
            $fallback = $settings->self_approval_fallback_role_id
                ? EmployeeRole::find($settings->self_approval_fallback_role_id)?->name
                : null;

            return [
                'allowed'  => false,
                'reason'   => 'You cannot approve your own cash advance. Self-approval is disabled in Cash Advance Settings.'
                            . ($fallback ? ' Ask a holder of the "' . $fallback . '" role to review it.' : ''),
                'approval' => null,
            ];
        }

        return ['allowed' => true, 'reason' => '', 'approval' => $approval];
    }

    /**
     * Setujui langkah yang sedang menunggu.
     *
     * @return array{allowed: bool, reason: string, completed: bool}
     */
    public function approve(CashAdvance $request, int $actorId, array $payload = [], bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);

        if (! $gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason'], 'completed' => false];
        }

        /** @var CashAdvanceApproval $approval */
        $approval = $gate['approval'];

        $completed = DB::transaction(function () use ($request, $approval, $actorId, $payload) {
            if ($actorId === (int) $request->employee_id) {
                $this->addFlag($request, self::FLAG_SELF_APPROVED, $actorId);
            }

            if ($this->periodLocked($request->request_date)) {
                $this->addFlag($request, self::FLAG_LOCKED_PERIOD);
            }

            $approval->update([
                'status'   => CashAdvanceApproval::STATUS_APPROVED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $payload['notes'] ?? null,
            ]);

            $next = $request->approvals()
                            ->where('order_seq', '>', $approval->order_seq)
                            ->where('status', CashAdvanceApproval::STATUS_WAITING)
                            ->orderBy('order_seq')
                            ->first();

            if ($next) {
                $request->update([
                    'status'             => CashAdvance::STATUS_IN_REVIEW,
                    'current_step_order' => $next->order_seq,
                ]);

                return false;
            }

            // Seluruh langkah lulus. Dokumennya `approved`, tetapi
            // `settlement_status` TETAP `unreported` — uang baru boleh keluar,
            // pertanggungjawabannya belum ada. Dua sumbu, dan di sinilah
            // pemisahan D140 terasa gunanya.
            $request->update([
                'status'             => CashAdvance::STATUS_APPROVED,
                'current_step_order' => null,
                'completed_at'       => now(),
            ]);

            return true;
        });

        $this->notify($request, $completed ? 'approved' : 'progressed', $payload['notes'] ?? null);

        return ['allowed' => true, 'reason' => '', 'completed' => $completed];
    }

    /**
     * Tolak dokumen. Menolak satu langkah menutup seluruh dokumen.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function reject(CashAdvance $request, int $actorId, string $notes, bool $canManage = false): array
    {
        $notes = trim($notes);

        if (mb_strlen($notes) < 5) {
            return [
                'allowed' => false,
                'reason'  => 'A rejection reason of at least 5 characters is required.',
            ];
        }

        $gate = $this->canAct($request, $actorId, $canManage);

        if (! $gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason']];
        }

        /** @var CashAdvanceApproval $approval */
        $approval = $gate['approval'];

        DB::transaction(function () use ($request, $approval, $actorId, $notes) {
            $approval->update([
                'status'   => CashAdvanceApproval::STATUS_REJECTED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $notes,
            ]);

            $request->approvals()
                    ->where('order_seq', '>', $approval->order_seq)
                    ->where('status', CashAdvanceApproval::STATUS_WAITING)
                    ->update(['status' => CashAdvanceApproval::STATUS_SKIPPED]);

            $request->update([
                'status'             => CashAdvance::STATUS_REJECTED,
                'current_step_order' => null,
                'completed_at'       => now(),
            ]);
        });

        $this->notify($request, 'rejected', $notes);

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PEMBACAAN
    // =======================================================================

    /** Riwayat dokumen milik seorang karyawan. */
    public function history(int $employeeId, int $limit = 50): Collection
    {
        return CashAdvance::with(['approvals', 'reports'])
            ->where('employee_id', $employeeId)
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ringkasan sebulan untuk kartu di halaman ESS.
     *
     * @return array<string, int|float>
     */
    public function monthlySummary(int $employeeId, int $year, int $month): array
    {
        $rows = CashAdvance::where('employee_id', $employeeId)
            ->forPeriod($year, $month)
            ->get(['status', 'amount', 'settlement_status']);

        return [
            'total'       => $rows->count(),
            'submitted'   => $rows->where('status', CashAdvance::STATUS_SUBMITTED)->count(),
            'in_review'   => $rows->where('status', CashAdvance::STATUS_IN_REVIEW)->count(),
            'approved'    => $rows->where('status', CashAdvance::STATUS_APPROVED)->count(),
            'rejected'    => $rows->where('status', CashAdvance::STATUS_REJECTED)->count(),
            'cancelled'   => $rows->where('status', CashAdvance::STATUS_CANCELLED)->count(),
            'amount'      => (float) $rows->whereIn('status', [
                CashAdvance::STATUS_SUBMITTED,
                CashAdvance::STATUS_IN_REVIEW,
                CashAdvance::STATUS_APPROVED,
            ])->sum('amount'),
            // Uang yang sudah keluar tetapi bukunya belum ditutup — angka yang
            // paling berguna bagi pemohon maupun bagian keuangan.
            'outstanding' => (float) $rows->where('status', CashAdvance::STATUS_APPROVED)
                                          ->where('settlement_status', '!=', CashAdvance::SETTLE_SETTLED)
                                          ->sum('amount'),
        ];
    }

    /**
     * Id dokumen yang sedang menunggu tindakan orang ini.
     *
     * Dipakai kartu "Waiting for You" dan penandaan baris di rekap. Dihitung
     * sekali lalu dipakai berulang, bukan dipanggil per baris.
     *
     * @return array<int>
     */
    public function pendingIdsFor(int $actorId): array
    {
        $roleIds = $this->roleIdsOf($actorId);

        return CashAdvance::open()
            ->with('approvals')
            ->get()
            ->filter(function (CashAdvance $request) use ($actorId, $roleIds) {
                $approval = $request->currentApproval();

                return $approval
                    && $approval->isWaiting()
                    && $approval->allows($actorId, $roleIds);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    /**
     * Langkah alur CA yang aktif.
     *
     * 🔴 forModule() WAJIB. Tanpa itu, langkah milik CAR ikut tersalin ke dokumen
     * CA — dan hasilnya bukan galat, melainkan dokumen yang menunggu persetujuan
     * orang yang tidak pernah diminta menyetujuinya (Keputusan D136).
     */
    public function activeSteps(): Collection
    {
        return CashAdvanceApprovalStep::query()
            ->forModule(CashAdvanceApprovalStep::MODULE_CA)
            ->active()
            ->ordered()
            ->get();
    }

    /**
     * Apakah periode akuntansi tanggal ini sudah dikunci?
     *
     * Memakai mesin periode yang SUDAH ADA (siklus 21-20), bukan membangun
     * ulang — pola yang sama dengan tiga sub-modul sebelumnya.
     */
    public function periodLocked($date): bool
    {
        $date   = $date instanceof Carbon ? $date : Carbon::parse($date);
        $coords = ReportingPeriod::periodFor($date);

        return ReportingPeriod::isClosed($coords['year'], $coords['month']);
    }

    /**
     * Kolom tanda tangan pada cetakan — EMPAT, dan sumbernya berbeda-beda.
     *
     * Requester   dari dokumen
     * Accounting  dari setelan
     * Cashier     dari setelan
     * Approved by 🔴 dari orang yang BENAR-BENAR menyetujui pada langkah
     *             ber-actor_role = approver TERAKHIR (Keputusan D129 & D137)
     *
     * Kolom keempat sengaja TIDAK diambil dari setelan. Menyimpan penanda tangan
     * di dua tempat hanya melahirkan satu kelas kesalahan baru: setelan berkata
     * A, riwayat persetujuan berkata B, dan yang tercetak adalah kertas yang
     * ditandatangani orang sungguhan.
     *
     * @return array<int, array{title: string, name: string, pending: bool}>
     */
    public function signatureColumns(CashAdvance $request): array
    {
        $settings = CashAdvanceSetting::current();

        $columns = [[
            'title'   => 'Requester',
            'name'    => $this->nameOf($request->employee),
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

        $columns[] = $this->approverSignatureColumn($request);

        return $columns;
    }

    /**
     * Kolom "Approved by," — isinya bergantung keadaan dokumen.
     *
     * Langkah berstatus `skipped` DILEWATI: dokumen yang dibatalkan atau ditolak
     * tidak boleh mencetak nama orang yang tidak akan pernah bertindak.
     *
     * @return array{title: string, name: string, pending: bool}
     */
    private function approverSignatureColumn(CashAdvance $request): array
    {
        $approverSteps = $request->approvals
            ->where('actor_role', CashAdvanceApprovalStep::ACTOR_APPROVER)
            ->where('status', '!=', CashAdvanceApproval::STATUS_SKIPPED);

        // Yang TERAKHIR menyetujui, bukan yang pertama: pada alur berjenjang,
        // nama yang pantas tercetak di "Approved by" adalah pemutus akhir.
        $acted = $approverSteps->where('status', CashAdvanceApproval::STATUS_APPROVED)
                               ->sortByDesc('order_seq')
                               ->first();

        if ($acted) {
            return [
                'title'   => 'Approved by',
                'name'    => $this->nameOf($acted->actor),
                'pending' => false,
            ];
        }

        // Belum ada yang menyetujui. Bila kandidatnya tunggal, namanya tetap
        // dicetak (abu, lewat flag `pending`) supaya pembaca dokumen tahu siapa
        // yang sedang ditunggu. Bila kandidatnya banyak, kolomnya dikosongkan —
        // menebak satu nama dari beberapa akan mencetak orang yang salah.
        $waiting = $approverSteps->sortBy('order_seq')->first();
        $name    = '';

        if ($waiting) {
            $ids  = array_map('intval', $waiting->approver_employee_ids ?? []);
            $name = count($ids) === 1
                ? $this->nameOf(Employee::with('basicData')->find($ids[0]))
                : '';
        }

        return ['title' => 'Approved by', 'name' => $name, 'pending' => true];
    }

    /**
     * Baris identitas perusahaan di kepala cetakan (Keputusan D113).
     *
     * 🔴 Dokumen yang dibebankan ke PROYEK tetap memakai nama perusahaan: nama
     * proyek adalah pihak yang dibebani, bukan pihak yang menerbitkan dokumen.
     */
    public function documentHeading(CashAdvance $request): string
    {
        $settings = CashAdvanceSetting::current();

        if ($settings->use_branch_name_in_header
            && $request->charged_branch_id
            && $request->branch) {
            return $request->branch->name;
        }

        return $settings->company_name;
    }

    /**
     * Pilihan pembebanan untuk dropdown, beserta label yang siap dibekukan.
     *
     * Dua daftar terpisah, bukan satu daftar gabungan: form menampilkannya
     * sebagai dua dropdown berpasangan (tipe -> daftar), dan menggabungkannya
     * akan memaksa JavaScript memisahkannya lagi.
     *
     * 🔴 Hanya proyek `is_closed = 0` yang muncul. Proyek yang ditutup SETELAH
     * dokumen dibuat tidak memengaruhi apa pun — labelnya sudah dibekukan.
     *
     * @return array{branch: array<int, array{id: int, label: string}>, project: array<int, array{id: int, label: string}>}
     */
    public function costCenterOptions(): array
    {
        $settings = CashAdvanceSetting::current();
        $out      = ['branch' => [], 'project' => []];

        if ($settings->allowsCostCenterType(CashAdvance::COST_CENTER_BRANCH)) {
            $out['branch'] = Branch::where('is_active', 1)
                ->orderBy('name')
                ->get()
                ->map(fn (Branch $b) => ['id' => (int) $b->id, 'label' => $this->branchLabel($b)])
                ->all();
        }

        if ($settings->allowsCostCenterType(CashAdvance::COST_CENTER_PROJECT)) {
            $out['project'] = DeliveryProject::where('is_closed', 0)
                ->orderBy('name')
                ->get()
                ->map(fn (DeliveryProject $p) => ['id' => (int) $p->id, 'label' => $this->projectLabel($p)])
                ->all();
        }

        return $out;
    }

    // =======================================================================
    // GERBANG & PEMBANTU
    // =======================================================================

    /**
     * Aturan tanggal pengajuan.
     *
     * @return array{allowed: bool, reason: string}
     */
    private function checkDateRules(Carbon $date, CashAdvanceSetting $settings, bool $byAdmin): array
    {
        $today = Carbon::today();

        // 🔴 Bawaannya TRUE di modul ini — kebalikan Reimbursement. CA meminta
        // uang untuk kegiatan yang BELUM terjadi, jadi tanggal masa depan normal.
        if (! $settings->allow_future_date && $date->gt($today)) {
            return [
                'allowed' => false,
                'reason'  => 'Future dates are not allowed. Enable them in Cash Advance Settings if needed.',
            ];
        }

        if ($settings->hasBackdateLimit()) {
            $earliest = $today->copy()->subDays((int) $settings->max_backdate_days);

            if ($date->lt($earliest)) {
                return [
                    'allowed' => false,
                    'reason'  => 'The date is older than the allowed ' . $settings->max_backdate_days . ' days.',
                ];
            }
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
     * Tanggal kedua — field OPTIONAL pada form acuan.
     *
     * @return array{allowed: bool, reason: string}
     */
    private function checkDateRange(Carbon $from, mixed $to, CashAdvanceSetting $settings): array
    {
        if (! $to) {
            return ['allowed' => true, 'reason' => ''];
        }

        if (! $settings->allow_date_range) {
            return [
                'allowed' => false,
                'reason'  => 'Date ranges are disabled in Cash Advance Settings.',
            ];
        }

        if (Carbon::parse($to)->lt($from)) {
            return [
                'allowed' => false,
                'reason'  => 'The end date cannot be earlier than the start date.',
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Setelan `require_car_before_new_ca` — menahan CA baru selama masih ada CA
     * yang belum dipertanggungjawabkan.
     *
     * Bawaannya mati, supaya tidak mengunci pengguna pertama. Dibaca DI SINI dan
     * hanya di sini; tanpa pembacaan ini setelannya jadi setelan mati (D52).
     *
     * @return array{allowed: bool, reason: string}
     */
    private function checkOutstandingRule(int $employeeId, CashAdvanceSetting $settings): array
    {
        if (! $settings->require_car_before_new_ca) {
            return ['allowed' => true, 'reason' => ''];
        }

        $outstanding = CashAdvance::where('employee_id', $employeeId)
            ->outstanding()
            ->orderBy('request_date')
            ->first();

        if (! $outstanding) {
            return ['allowed' => true, 'reason' => ''];
        }

        return [
            'allowed' => false,
            'reason'  => 'Cash advance ' . $outstanding->request_no
                         . ' has not been settled yet. Submit its cash advance report before requesting a new one.',
        ];
    }

    /**
     * Bukti pendukung — TAUTAN, bukan unggahan (pola `supporting_url`
     * Reimbursement). Nol unggahan berkas di modul ini.
     *
     * @return array{allowed: bool, reason: string}
     */
    private function checkDetailUrl(?string $url, CashAdvanceSetting $settings): array
    {
        $url = trim((string) $url);

        if ($url === '') {
            return $settings->require_detail_url
                ? ['allowed' => false, 'reason' => 'A supporting document link is required.']
                : ['allowed' => true, 'reason' => ''];
        }

        $host  = strtolower((string) parse_url($url, PHP_URL_HOST));
        $hosts = $settings->allowedUrlHosts();

        if ($host === '') {
            return ['allowed' => false, 'reason' => 'The supporting link is not a valid URL.'];
        }

        // Daftar kosong berarti "host apa pun boleh" — katup pengaman supaya
        // setelan yang terlanjur dikosongkan tidak menolak seluruh pengajuan.
        if ($hosts === []) {
            return ['allowed' => true, 'reason' => ''];
        }

        foreach ($hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return ['allowed' => true, 'reason' => ''];
            }
        }

        return [
            'allowed' => false,
            'reason'  => 'The supporting link must point to one of: ' . implode(', ', $hosts) . '.',
        ];
    }

    /**
     * Menentukan pembebanan dokumen dan MEMBEKUKAN labelnya.
     *
     * 🔴 Satu dokumen hanya boleh mengisi SATU kolom pembebanan. Kolom yang lain
     * dipaksa NULL di server, bukan sekadar disembunyikan di layar — pengguna
     * yang berpindah Branch↔Project meninggalkan nilai lama di form, dan
     * membiarkannya tersimpan menghasilkan dokumen yang dibebankan ke dua tempat.
     *
     * @return array{allowed: bool, reason: string, type: ?string, branch_id: ?int, project_id: ?int, label: ?string}
     */
    private function resolveCostCenter(array $payload, CashAdvanceSetting $settings): array
    {
        $empty = [
            'allowed' => true, 'reason' => '',
            'type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null,
        ];

        $type = $payload['cost_center_type'] ?? null;

        if (! $type) {
            return $settings->require_cost_center
                ? ['allowed' => false, 'reason' => 'Please choose what this cash advance is charged to.']
                  + ['type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null]
                : $empty;
        }

        if (! $settings->allowsCostCenterType($type)) {
            return ['allowed' => false, 'reason' => 'That cost center type is not enabled in Cash Advance Settings.']
                   + ['type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null];
        }

        if ($type === CashAdvance::COST_CENTER_BRANCH) {
            $branch = Branch::where('is_active', 1)->find($payload['charged_branch_id'] ?? null);

            if (! $branch) {
                return ['allowed' => false, 'reason' => 'Please choose a valid active branch.']
                       + ['type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null];
            }

            return [
                'allowed' => true, 'reason' => '',
                'type' => $type, 'branch_id' => (int) $branch->id,
                'project_id' => null, 'label' => $this->branchLabel($branch),
            ];
        }

        $project = DeliveryProject::where('is_closed', 0)->find($payload['charged_project_id'] ?? null);

        if (! $project) {
            return ['allowed' => false, 'reason' => 'Please choose a valid open project.']
                   + ['type' => null, 'branch_id' => null, 'project_id' => null, 'label' => null];
        }

        return [
            'allowed' => true, 'reason' => '',
            'type' => $type, 'branch_id' => null,
            'project_id' => (int) $project->id, 'label' => $this->projectLabel($project),
        ];
    }

    private function branchLabel(Branch $branch): string
    {
        return trim(($branch->code ? $branch->code . ' – ' : '') . $branch->name);
    }

    /**
     * `io_number` dipakai karena itulah padanan terdekat kode dokumen pada
     * aplikasi acuan, dan karena nama proyek terlalu panjang untuk berdiri
     * sendiri di kolom cetakan.
     */
    private function projectLabel(DeliveryProject $project): string
    {
        return trim(($project->io_number ? $project->io_number . ' – ' : '') . $project->name);
    }

    /**
     * Memvalidasi pilihan Approver dari form.
     *
     * 🔴 INILAH PERBAIKAN ATAS CACAT ACUAN (Keputusan D138). Pada Ferosoft,
     * server menuntut `approver_employee_id` sementara formnya tidak pernah
     * merender kontrolnya — pengguna tidak punya cara untuk berhasil. Di sini
     * yang menentukan adalah kolom `requester_selectable` per langkah: bila
     * mati, field-nya tidak dikirim DAN tidak divalidasi.
     *
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

            // Langkah selectable tanpa kandidat seharusnya sudah ditolak di
            // halaman Settings. Diperiksa lagi di sini karena data dapat berubah
            // di antara keduanya — role yang kandidatnya habis, misalnya.
            if ($candidates === []) {
                return [
                    'allowed' => false,
                    'reason'  => 'Step "' . $step->name . '" has no approver to choose from. Ask an administrator to fix it in Cash Advance Settings.',
                    'chosen'  => [],
                ];
            }

            // Kandidat tunggal tidak perlu dikirim dari form: dropdown-nya
            // terkunci, dan memaksa form mengirimnya hanya menambah satu cara
            // untuk gagal.
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

    /**
     * Salin definisi langkah ke dokumen.
     *
     * 🔴 `actor_role` ikut DIBEKUKAN. Kolom itulah yang menentukan siapa
     * tercetak di "Approved by"; kalau dibaca dari konfigurasi saat mencetak,
     * mencetak ULANG dokumen lama setelah alur diubah menghasilkan kertas
     * berbeda — dengan nama orang yang keliru di kolom persetujuan.
     *
     * 🔴 Untuk langkah yang penyetujunya dipilih pemohon, `approver_employee_ids`
     * diisi TEPAT SATU id, bukan seluruh daftar kandidat. Itulah mekanisme
     * pembekuan D126.
     *
     * @param  array<int, int>  $chosen  [order_seq => employee_id]
     */
    private function snapshotSteps(CashAdvance $request, Collection $steps, array $chosen = []): void
    {
        foreach ($steps as $step) {
            $pick = $chosen[$step->order_seq] ?? null;

            CashAdvanceApproval::create([
                'cash_advance_id'       => $request->id,
                'order_seq'             => $step->order_seq,
                'step_name'             => $step->name,
                'actor_role'            => $step->actor_role,
                'approver_type'         => $step->approver_type,
                'approver_role_id'      => $step->approver_role_id,
                'approver_employee_ids' => $pick !== null ? [$pick] : $step->approver_employee_ids,
                'chosen_by_requester'   => $pick !== null,
                'status'                => CashAdvanceApproval::STATUS_WAITING,
            ]);
        }
    }

    /**
     * Berapa dokumen berjalan yang akan terkena bila satu langkah baru
     * ditambahkan pada urutan `$orderSeq`.
     *
     * Dipakai halaman Settings untuk menyebut angkanya SEBELUM tombolnya
     * ditekan — keputusan yang menyentuh dokumen berjalan tidak boleh diambil
     * tanpa tahu berapa banyak yang tersentuh.
     */
    public function countOpenRequestsBefore(int $orderSeq): int
    {
        return CashAdvance::query()
            ->whereIn('status', CashAdvance::OPEN_STATUSES)
            ->where('current_step_order', '<', $orderSeq)
            ->count();
    }

    /**
     * Terapkan satu langkah persetujuan BARU ke dokumen yang sedang berjalan.
     *
     * 🔴 ATURANNYA ASIMETRIS, DAN ITU DISENGAJA (Keputusan D116).
     *
     * Menambah langkah berarti MEMPERKETAT kontrol. Memperketat tidak pernah
     * merusak persetujuan yang sudah terjadi, dan justru dokumen yang sedang
     * berjalan-lah yang paling perlu diamankan — orang menambah penyetuju
     * biasanya karena baru menyadari ada celah, bukan untuk bulan depan.
     *
     * Sebaliknya MENGHAPUS atau MELONGGARKAN langkah TIDAK PERNAH berlaku surut.
     * Di sanalah bahayanya: dokumen yang menunggu di langkah yang dihapus bisa
     * melompat jadi disetujui tanpa ditinjau siapa pun.
     *
     * Batas yang dijaga:
     *  - HANYA dokumen berstatus terbuka
     *  - HANYA bila langkah barunya berada SESUDAH langkah yang sedang menunggu
     *  - Baris riwayat yang sudah bertindak TIDAK PERNAH ditulis ulang
     *  - Anti-duplikat pada `order_seq` yang sama
     *
     * 🔴 Langkah `requester_selectable` yang diterapkan surut TIDAK dapat
     * menanyakan pilihan kepada pemohon — dokumennya sudah berjalan. Karena itu
     * ia disalin dengan seluruh kandidatnya dan `chosen_by_requester = false`;
     * perilakunya jatuh ke tipe aslinya. Memaksa pilihan yang tidak pernah
     * diberikan pemohon jauh lebih buruk daripada memakai definisi langkahnya.
     *
     * @return array{applied: int, request_nos: array<string>}
     */
    public function applyStepToOpenRequests(CashAdvanceApprovalStep $step, ?int $actorId = null): array
    {
        $targets = CashAdvance::query()
            ->whereIn('status', CashAdvance::OPEN_STATUSES)
            ->where('current_step_order', '<', $step->order_seq)
            ->get();

        if ($targets->isEmpty()) {
            return ['applied' => 0, 'request_nos' => []];
        }

        $applied = [];

        DB::transaction(function () use ($targets, $step, &$applied) {
            foreach ($targets as $request) {
                if ($request->approvals()->where('order_seq', $step->order_seq)->exists()) {
                    continue;
                }

                CashAdvanceApproval::create([
                    'cash_advance_id'       => $request->id,
                    'order_seq'             => $step->order_seq,
                    'step_name'             => $step->name,
                    'actor_role'            => $step->actor_role,
                    'approver_type'         => $step->approver_type,
                    'approver_role_id'      => $step->approver_role_id,
                    'approver_employee_ids' => $step->approver_employee_ids,
                    'chosen_by_requester'   => false,
                    'status'                => CashAdvanceApproval::STATUS_WAITING,
                ]);

                // Ditandai NETRAL: dokumen ini alurnya diperpanjang di tengah
                // jalan. Bukan anomali, tetapi harus dapat ditelusuri kalau kelak
                // ada yang bertanya kenapa dokumennya melewati langkah tambahan.
                $this->addFlag($request, 'workflow_extended', $step->order_seq);

                $applied[] = $request->request_no;
            }
        });

        if ($applied !== []) {
            Log::info('Cash advance approval step applied to in-progress documents.', [
                'actor_id'    => $actorId,
                'step_name'   => $step->name,
                'order_seq'   => $step->order_seq,
                'applied'     => count($applied),
                'request_nos' => $applied,
            ]);
        }

        return ['applied' => count($applied), 'request_nos' => $applied];
    }

    /**
     * Nomor dokumen berikutnya: CA/2026/09/00001 (Keputusan D144).
     *
     * Mengikuti konvensi EcoSystem — RB/… Reimbursement, PR/… Purchase Request —
     * BUKAN bentuk `26/IX/CA/00002` aplikasi acuan. Urutan reset tiap bulan.
     *
     * 🔴 Dipanggil di dalam transaksi ber-lockForUpdate(): dua pengajuan
     * bersamaan tidak boleh mendapat nomor yang sama (Keputusan D92). Indeks unik
     * pada kolomnya adalah jaring pengaman terakhir, bukan penjagaan utamanya.
     */
    private function nextRequestNo(Carbon $date): string
    {
        $prefix = sprintf('CA/%s/%s/', $date->format('Y'), $date->format('m'));

        $last = CashAdvance::withTrashed()
            ->where('request_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('request_no')
            ->value('request_no');

        $next = $last ? ((int) substr($last, -5)) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Menempelkan sinyal anomali tanpa menimpa sinyal lain.
     *
     * Bentuknya PETA (kunci => nilai), bukan daftar seperti di Purchase Request,
     * karena beberapa sinyal modul ini membawa keterangan: batas yang dilampaui,
     * nominal sebelum dan sesudah diedit, siapa yang menyetujui dokumennya
     * sendiri. Daftar datar akan kehilangan semua itu.
     */
    private function addFlag(CashAdvance $request, string $key, mixed $value = true): void
    {
        $flags = $request->flags ?? [];

        if (! array_key_exists($key, $flags)) {
            $flags[$key] = $value;
            $request->update(['flags' => $flags]);
        }
    }

    /** @return array<int> */
    private function roleIdsOf(int $employeeId): array
    {
        return Employee::where('employee_id', $employeeId)->first()?->getRoleIds() ?? [];
    }

    /** Nama yang ditampilkan; pola yang sama dengan ReimbursementService. */
    private function nameOf(?Employee $employee): string
    {
        return $employee?->basicData?->nick_name ?? $employee?->eci ?? '—';
    }

    /**
     * Beri tahu karyawan perkembangan dokumennya (jawaban C12).
     *
     * Dibungkus try/catch karena kegagalan mengirim notifikasi tidak boleh
     * membatalkan persetujuan yang SUDAH tersimpan — tetapi tetap dicatat ke log
     * supaya kegagalannya tidak hilang diam-diam (Keputusan D44).
     */
    private function notify(CashAdvance $request, string $outcome, ?string $notes = null): void
    {
        try {
            $amount = $this->amounts->format((float) $request->amount);

            $message = match ($outcome) {
                'approved'   => "Your cash advance {$request->request_no} ({$request->currency} {$amount}) was approved.",
                'rejected'   => "Your cash advance {$request->request_no} ({$request->currency} {$amount}) was rejected.",
                'progressed' => "Your cash advance {$request->request_no} passed a review step.",
                default      => "Your cash advance {$request->request_no} was updated.",
            };

            Notification::create([
                'employee_id'      => $request->employee_id,
                'type'             => 'cash_advance_' . $outcome,
                'from_employee_id' => session('user.id'),
                'preview'          => $message . ($notes ? ' Note: ' . $notes : ''),
                'link'             => '/general/my-cash-advance',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send cash advance notification.', [
                'request_id' => $request->id,
                'outcome'    => $outcome,
                'message'    => $e->getMessage(),
            ]);
        }
    }
}
