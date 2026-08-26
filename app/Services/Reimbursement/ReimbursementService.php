<?php

namespace App\Services\Reimbursement;

use App\Models\Attendance\Branch;
use App\Models\Employee;
use App\Models\Notification;
use App\Models\Reimbursement\ReimbursementApprovalStep;
use App\Models\Reimbursement\ReimbursementItem;
use App\Models\Reimbursement\ReimbursementRequest;
use App\Models\Reimbursement\ReimbursementRequestApproval;
use App\Models\Reimbursement\ReimbursementSetting;
use App\Models\ReportingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin pengajuan dan persetujuan reimbursement.
 *
 * AGNOSTIK TERHADAP TRANSPORT: menerima array biasa, bukan Request, sehingga bila
 * kelak endpoint mobile atau impor Excel dibuka, cukup menambah controller tipis
 * tanpa menyentuh aturan bisnisnya. Pola yang sama dipakai AttendanceService dan
 * OvertimeService.
 *
 * Seluruh gerbang bisnis mengembalikan ['allowed' => bool, 'reason' => string]
 * meniru PeriodService yang sudah jadi konvensi di basis kode ini.
 *
 * 🔴 `employee_id` SELALU diterima sebagai argumen dari pemanggil yang
 * mengambilnya dari sesi — tidak pernah dari badan request. Ini yang mencegah
 * seseorang mengajukan reimbursement atas nama rekannya. Satu-satunya
 * pengecualian adalah pembuatan oleh admin ("New RB"), yang menyebut karyawannya
 * secara eksplisit DAN mencatat pembuatnya di `created_by`.
 *
 * PEMBAGIAN TUGAS dengan ReimbursementTotalService: yang murni menghitung dan
 * MENANDAI ada di sana; yang MENOLAK ada di sini, karena penolakan selalu
 * menyangkut keadaan di luar item — periode terkunci, langkah persetujuan, dan
 * kebijakan batas nominal.
 */
class ReimbursementService
{
    public function __construct(private ReimbursementTotalService $totals)
    {
    }

    // =======================================================================
    // PENGAJUAN
    // =======================================================================

    /**
     * Ajukan reimbursement.
     *
     * @param  array{request_date: string, title: string, supporting_url: ?string, items: array}  $payload
     * @param  int|null  $createdBy  Diisi HANYA bila admin membuat atas nama karyawan.
     * @return array{allowed: bool, reason: string, request: ?ReimbursementRequest}
     */
    public function submit(int $employeeId, array $payload, ?int $createdBy = null): array
    {
        $settings = ReimbursementSetting::current();
        $date     = Carbon::parse($payload['request_date'])->startOfDay();

        $gate = $this->checkDateRules($date, $settings, $createdBy !== null);
        if (!$gate['allowed']) {
            return $gate + ['request' => null];
        }

        $items = $this->normaliseItems($payload['items'] ?? []);

        $gate = $this->checkItemRules($items, $settings);
        if (!$gate['allowed']) {
            return $gate + ['request' => null];
        }

        $evaluated = $this->totals->evaluate($items, $this->rulesFrom($settings, $payload));

        // Satu-satunya aturan item yang dapat MENOLAK, dan hanya bila pemilik
        // sistem memilih mode `block` (Keputusan D107).
        if ($settings->blocksOverLimit()
            && $this->totals->exceedsLimit($evaluated['total'], $settings->max_request_amount)) {

            return [
                'allowed' => false,
                'reason'  => 'The total of ' . ReimbursementRequest::formatRupiah($evaluated['total'])
                           . ' exceeds the maximum of '
                           . ReimbursementRequest::formatRupiah($settings->max_request_amount)
                           . '. Ask HR to raise the limit in Reimbursement Settings.',
                'request' => null,
            ];
        }

        // Langkah persetujuan harus ada SEBELUM dokumen dibuat. Tanpa satu pun
        // langkah aktif, dokumen akan lahir tanpa jalan keluar — tidak dapat
        // disetujui maupun ditolak siapa pun.
        $steps = $this->activeSteps();
        if ($steps->isEmpty()) {
            return [
                'allowed' => false,
                'reason'  => 'No approval step is configured yet. Ask HR to set one up in Reimbursement Settings.',
                'request' => null,
            ];
        }

        $flags = $evaluated['flags'];
        if ($createdBy !== null && $createdBy !== $employeeId) {
            $flags[] = ReimbursementRequest::FLAG_CREATED_ON_BEHALF;
        }

        $request = DB::transaction(function () use (
            $employeeId, $createdBy, $date, $payload, $items, $evaluated, $flags, $steps
        ) {
            $request = ReimbursementRequest::create([
                'request_no'        => $this->nextRequestNo($date),
                'employee_id'       => $employeeId,
                'created_by'        => $createdBy,
                'request_date'      => $date->toDateString(),
                'title'             => $payload['title'],
                'supporting_url'    => $payload['supporting_url'] ?? null,
                'charged_branch_id' => $evaluated['charged_branch_id'],
                'charged_to_label'  => $evaluated['charged_to_label'],
                'currency'          => 'IDR',
                'total_amount'      => $evaluated['total'],
                'item_count'        => $evaluated['item_count'],
                'status'            => ReimbursementRequest::STATUS_SUBMITTED,
                'current_step_order' => $steps->first()->order_seq,
                'flags'             => array_values(array_unique($flags)),
                'period_year'       => (int) $date->format('Y'),
                'period_month'      => (int) $date->format('n'),
            ]);

            $this->writeItems($request, $items);
            $this->snapshotSteps($request, $steps);

            return $request;
        });

        return [
            'allowed' => true,
            'reason'  => '',
            'request' => $request->fresh(['items', 'approvals']),
        ];
    }

    /**
     * Ubah dokumen yang masih berjalan (sisi HR, slug `.manage`).
     *
     * Item ditulis ulang seluruhnya, bukan ditambal satu-satu: form mengirim
     * keadaan akhir yang dikehendaki, dan mencocokkan baris lama dengan baris
     * baru satu per satu hanya menambah jalan bagi total menyimpang dari isinya.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function update(ReimbursementRequest $request, array $payload, int $actorId): array
    {
        if (!$request->isEditable()) {
            return [
                'allowed' => false,
                'reason'  => 'This document is already closed and can no longer be edited.',
            ];
        }

        $settings = ReimbursementSetting::current();
        $items    = $this->normaliseItems($payload['items'] ?? []);

        $gate = $this->checkItemRules($items, $settings);
        if (!$gate['allowed']) {
            return $gate;
        }

        $evaluated = $this->totals->evaluate($items, $this->rulesFrom($settings, $payload));

        $previousTotal = (float) $request->total_amount;

        DB::transaction(function () use ($request, $payload, $items, $evaluated, $previousTotal, $actorId) {
            $flags = $evaluated['flags'];

            // Flag netral yang tidak lahir dari item dipertahankan: ia mencatat
            // fakta historis, bukan keadaan isi dokumen saat ini.
            foreach ([
                ReimbursementRequest::FLAG_CREATED_ON_BEHALF,
                ReimbursementRequest::FLAG_SELF_APPROVED,
                ReimbursementRequest::FLAG_LOCKED_PERIOD,
            ] as $sticky) {
                if ($request->hasFlag($sticky)) {
                    $flags[] = $sticky;
                }
            }

            if (abs($evaluated['total'] - $previousTotal) > 0.001) {
                $flags[] = ReimbursementRequest::FLAG_AMOUNT_ADJUSTED;
            }

            $request->update([
                'title'             => $payload['title'] ?? $request->title,
                'supporting_url'    => $payload['supporting_url'] ?? null,
                'charged_branch_id' => $evaluated['charged_branch_id'],
                'charged_to_label'  => $evaluated['charged_to_label'],
                'total_amount'      => $evaluated['total'],
                'item_count'        => $evaluated['item_count'],
                'flags'             => array_values(array_unique($flags)),
            ]);

            $request->items()->delete();
            $this->writeItems($request, $items);
        });

        // Perubahan nominal pada dokumen yang berujung ke pembayaran harus dapat
        // ditelusuri di luar tabelnya sendiri (pola Keputusan D53).
        if (abs($evaluated['total'] - $previousTotal) > 0.001) {
            Log::info('Reimbursement amount changed by an administrator.', [
                'request_no' => $request->request_no,
                'actor_id'   => $actorId,
                'before'     => $previousTotal,
                'after'      => $evaluated['total'],
            ]);
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Hapus dokumen — SOFT DELETE beserta alasannya (Keputusan D109).
     *
     * Aplikasi acuan menghapusnya permanen; itu sengaja tidak diikuti. Dokumen
     * yang berujung ke pembayaran tidak boleh lenyap tanpa jejak: bila barisnya
     * hilang, tidak ada yang dapat menjawab dokumen mana yang hilang, siapa yang
     * menghapusnya, dan atas dasar apa.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function softDelete(ReimbursementRequest $request, int $actorId, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            return [
                'allowed' => false,
                'reason'  => 'Please state why this document is being deleted.',
            ];
        }

        $wasApproved = $request->status === ReimbursementRequest::STATUS_APPROVED;

        DB::transaction(function () use ($request, $actorId, $reason) {
            $request->update([
                'deleted_by'    => $actorId,
                'delete_reason' => $reason,
            ]);

            $request->delete();
        });

        // Menghapus dokumen yang SUDAH disetujui berarti menghapus dasar
        // pembayaran. Tetap diizinkan — HR yang bertanggung jawab — tetapi
        // dicatat menonjol supaya pertanyaan auditor selalu ada jawabannya.
        if ($wasApproved) {
            Log::warning('An APPROVED reimbursement document was deleted.', [
                'request_no' => $request->request_no,
                'employee_id' => $request->employee_id,
                'amount'     => (float) $request->total_amount,
                'actor_id'   => $actorId,
                'reason'     => $reason,
            ]);
        } else {
            Log::info('Reimbursement document deleted.', [
                'request_no' => $request->request_no,
                'status'     => $request->status,
                'actor_id'   => $actorId,
                'reason'     => $reason,
            ]);
        }

        return ['allowed' => true, 'reason' => ''];
    }

    // =======================================================================
    // PERSETUJUAN
    // =======================================================================

    /**
     * Bolehkah orang ini bertindak pada dokumen ini sekarang?
     *
     * Memeriksa EMPAT hal terpisah dengan pesan galat yang berbeda-beda, supaya
     * penyetuju tahu apa yang salah alih-alih menerima satu penolakan buram.
     *
     * @return array{allowed: bool, reason: string, approval: ?ReimbursementRequestApproval}
     */
    public function canAct(ReimbursementRequest $request, int $actorId, bool $canManage = false): array
    {
        if (!$request->isOpen()) {
            return [
                'allowed'  => false,
                'reason'   => 'This document is already ' . $request->status . '.',
                'approval' => null,
            ];
        }

        $approval = $request->currentApproval();

        if (!$approval || !$approval->isWaiting()) {
            return [
                'allowed'  => false,
                'reason'   => 'There is no approval step waiting on this document.',
                'approval' => null,
            ];
        }

        $settings = ReimbursementSetting::current();

        if ($this->periodLocked($request->request_date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === ReimbursementSetting::LOCK_BLOCK_ALL) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this document is locked, and the current policy blocks everyone.',
                    'approval' => null,
                ];
            }

            if ($policy === ReimbursementSetting::LOCK_BLOCK_EMPLOYEE && !$canManage) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this document is locked. Only holders of the manage permission can act on it.',
                    'approval' => null,
                ];
            }
        }

        if (!$approval->allows($actorId, $this->roleIdsOf($actorId))) {
            return [
                'allowed'  => false,
                'reason'   => 'This document is waiting for ' . $approval->step_name . ', which you are not an approver for.',
                'approval' => null,
            ];
        }

        if ($actorId === (int) $request->employee_id && !$settings->allow_self_approval) {
            return [
                'allowed'  => false,
                'reason'   => 'You cannot approve your own reimbursement. Self-approval is disabled in Reimbursement Settings.',
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
    public function approve(ReimbursementRequest $request, int $actorId, array $payload = [], bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);

        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason'], 'completed' => false];
        }

        /** @var ReimbursementRequestApproval $approval */
        $approval = $gate['approval'];

        $completed = DB::transaction(function () use ($request, $approval, $actorId, $payload) {
            if ($actorId === (int) $request->employee_id) {
                $this->addFlag($request, ReimbursementRequest::FLAG_SELF_APPROVED);
            }

            if ($this->periodLocked($request->request_date)) {
                $this->addFlag($request, ReimbursementRequest::FLAG_LOCKED_PERIOD);
            }

            $approval->update([
                'status'   => ReimbursementRequestApproval::STATUS_APPROVED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $payload['notes'] ?? null,
            ]);

            $next = $request->approvals()
                ->where('order_seq', '>', $approval->order_seq)
                ->where('status', ReimbursementRequestApproval::STATUS_WAITING)
                ->orderBy('order_seq')
                ->first();

            if ($next) {
                $request->update([
                    'status'             => ReimbursementRequest::STATUS_IN_REVIEW,
                    'current_step_order' => $next->order_seq,
                ]);

                return false;
            }

            $request->update([
                'status'             => ReimbursementRequest::STATUS_APPROVED,
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
    public function reject(ReimbursementRequest $request, int $actorId, string $notes, bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);

        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason']];
        }

        /** @var ReimbursementRequestApproval $approval */
        $approval = $gate['approval'];

        DB::transaction(function () use ($request, $approval, $actorId, $notes) {
            $approval->update([
                'status'   => ReimbursementRequestApproval::STATUS_REJECTED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $notes,
            ]);

            // Langkah yang belum dijalani ditandai `skipped`, bukan dibiarkan
            // `waiting`: dibiarkan menunggu, ia akan tampak seolah masih ada yang
            // harus bertindak pada dokumen yang sudah tertutup.
            $request->approvals()
                ->where('order_seq', '>', $approval->order_seq)
                ->where('status', ReimbursementRequestApproval::STATUS_WAITING)
                ->update(['status' => ReimbursementRequestApproval::STATUS_SKIPPED]);

            $request->update([
                'status'             => ReimbursementRequest::STATUS_REJECTED,
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
        return ReimbursementRequest::forEmployee($employeeId)
            ->with(['items', 'approvals.actor.basicData', 'approvals.role'])
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ringkasan bulan berjalan untuk kartu di halaman karyawan.
     *
     * Hanya JUMLAH DOKUMEN, tanpa total nominal. Kartu "Approved Amount"
     * dihapus dari kedua sisi mengikuti aplikasi acuan — dan angka yang tidak
     * ditampilkan tidak perlu dihitung di setiap pembukaan halaman. Nominal per
     * dokumen tetap terbaca di kolom Amount pada tabelnya.
     *
     * @return array{submitted: int, approved: int, pending: int}
     */
    public function monthlySummary(int $employeeId, int $year, int $month): array
    {
        $rows = ReimbursementRequest::forEmployee($employeeId)
            ->forPeriod($year, $month)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $status) => (int) ($rows[$status]->total ?? 0);

        return [
            'submitted' => $rows->sum('total'),
            'approved'  => $count(ReimbursementRequest::STATUS_APPROVED),
            'pending'   => collect(ReimbursementRequest::OPEN_STATUSES)->sum($count),
        ];
    }

    /**
     * Id dokumen yang menjadi giliran orang ini.
     *
     * Dihitung sekali lalu dipakai untuk MENANDAI baris dan untuk MENYARING —
     * supaya penyetuju tidak perlu menebak mana yang menunggu dirinya.
     *
     * @return array<int>
     */
    public function pendingIdsFor(int $actorId): array
    {
        $roleIds = $this->roleIdsOf($actorId);

        return ReimbursementRequest::open()
            ->with('approvals')
            ->get()
            ->filter(function (ReimbursementRequest $request) use ($actorId, $roleIds) {
                $approval = $request->currentApproval();

                return $approval && $approval->isWaiting() && $approval->allows($actorId, $roleIds);
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
    }

    /** Langkah persetujuan yang berlaku untuk dokumen baru. */
    public function activeSteps(): Collection
    {
        return ReimbursementApprovalStep::forReimbursement()
            ->active()
            ->orderBy('order_seq')
            ->get();
    }

    /** Apakah periode pelaporan untuk tanggal ini sudah ditutup? */
    public function periodLocked($date): bool
    {
        $date   = $date instanceof Carbon ? $date : Carbon::parse($date);
        $coords = ReportingPeriod::periodFor($date);

        return ReportingPeriod::isClosed($coords['year'], $coords['month']);
    }

    /**
     * Aturan validasi untuk baris item.
     *
     * Diletakkan di service, bukan disalin ke tiap controller, karena baris item
     * divalidasi di TIGA tempat: pengajuan mandiri, form "New RB" milik admin,
     * dan Edit milik HR. Aturan yang disalin akan menyimpang cepat atau lambat,
     * dan yang menyimpang di sini berarti dokumen keuangan lolos dengan isi yang
     * tidak sah lewat salah satu pintu.
     *
     * 🔴 Urutan tanggal nota TIDAK dapat diperiksa dengan
     * `after_or_equal:items.*.receipt_date_from` — Laravel tidak menyelesaikan
     * jalur berwildcard untuk aturan pembanding field, dan mencoba mengurai
     * teks "items.*.receipt_date_from" itu sendiri sebagai tanggal. Karena itu
     * dipakai closure yang mencari field sebelahnya lewat jalur atributnya.
     *
     * @param  array  $input  Seluruh masukan, dipakai closure mencari field sebelah.
     * @return array<string, array>
     */
    public function itemRules(ReimbursementSetting $settings, array $input): array
    {
        return [
            'items'                     => ['required', 'array', 'min:1'],
            'items.*.description'       => ['required', 'string', 'max:200'],
            'items.*.branch_id'         => ['required', 'integer', 'exists:branches,id'],
            'items.*.receipt_no'        => $settings->require_receipt_no
                                            ? ['required', 'string', 'max:50']
                                            : ['nullable', 'string', 'max:50'],
            'items.*.receipt_date_from' => ['required', 'date'],
            'items.*.receipt_date_to'   => ['nullable', 'date', $this->dateOrderRule($input)],
            'items.*.amount'            => ['required', 'numeric', 'min:1'],
        ];
    }

    /** Tanggal akhir nota tidak boleh mendahului tanggal awalnya. */
    private function dateOrderRule(array $input): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($input) {
            if (!$value) {
                return;
            }

            $from = data_get($input, str_replace('receipt_date_to', 'receipt_date_from', $attribute));

            if ($from && strtotime((string) $value) < strtotime((string) $from)) {
                $fail('The second receipt date cannot be earlier than the first one.');
            }
        };
    }

    /**
     * Nama yang tercetak di empat kolom tanda tangan.
     *
     * Dipakai bersama oleh halaman cetak dan berkas Excel, supaya keduanya tidak
     * pernah menampilkan nama yang berbeda untuk dokumen yang sama.
     *
     * Kolom "Approved by" memakai penanda tangan dari pengaturan bila diisi, dan
     * bila kosong jatuh ke penyetuju TERAKHIR YANG BENAR-BENAR MENYETUJUI —
     * bukan sekadar langkah terakhir yang terdaftar (Keputusan D108).
     *
     * @return array{requester: string, accounting: string, cashier: string, approver: string}
     */
    public function signatories(ReimbursementRequest $request): array
    {
        $settings = ReimbursementSetting::current();

        $name = fn (?Employee $e) => $e?->basicData?->nick_name ?? $e?->eci ?? '—';

        $approver = $settings->approverSigner;

        if (!$approver) {
            $lastApproved = $request->approvals
                ->where('status', ReimbursementRequestApproval::STATUS_APPROVED)
                ->sortByDesc('order_seq')
                ->first();

            $approver = $lastApproved?->actor;
        }

        return [
            'requester'  => $name($request->employee),
            'accounting' => $name($settings->accountingSigner),
            'cashier'    => $name($settings->cashierSigner),
            'approver'   => $name($approver),
        ];
    }

    /**
     * Baris identitas perusahaan di kepala cetakan dan Excel (Keputusan D113).
     *
     * Memakai nama cabang bila pengaturannya menghendaki DAN seluruh item berada
     * di satu cabang. Dokumen lintas cabang selalu jatuh ke nama perusahaan,
     * karena tidak ada satu cabang pun yang mewakilinya.
     */
    public function documentHeading(ReimbursementRequest $request): string
    {
        $settings = ReimbursementSetting::current();

        if ($settings->use_branch_name_in_header
            && $request->charged_branch_id
            && $request->chargedBranch) {

            return $request->chargedBranch->name;
        }

        return $settings->company_name;
    }

    /**
     * Cabang aktif untuk dropdown item, beserta labelnya yang sudah siap dibekukan.
     *
     * @return Collection<int, array{id: int, label: string}>
     */
    public function branchOptions(): Collection
    {
        return Branch::active()
            ->orderBy('name')
            ->get(['id', 'code', 'name'])
            ->map(fn (Branch $branch) => [
                'id'    => (int) $branch->id,
                'label' => $branch->code . ' – ' . $branch->name,
            ]);
    }

    // =======================================================================
    // internal
    // =======================================================================

    /** @return array{allowed: bool, reason: string} */
    private function checkDateRules(Carbon $date, ReimbursementSetting $settings, bool $byAdmin): array
    {
        if (!$settings->allow_future_date && $date->greaterThan(now()->startOfDay())) {
            return [
                'allowed' => false,
                'reason'  => 'Reimbursement cannot be submitted for a future date.',
            ];
        }

        if ($settings->hasBackdateLimit()
            && $date->lessThan(now()->startOfDay()->subDays($settings->max_backdate_days))) {

            return [
                'allowed' => false,
                'reason'  => 'Reimbursement can only be submitted for the last '
                           . $settings->max_backdate_days . ' days.',
            ];
        }

        // Periode terkunci: karyawan tertahan, admin pemegang hak kelola tidak.
        // `byAdmin` di sini berarti dokumen dibuat lewat "New RB", yang hanya
        // dapat diakses pemegang `general.reimbursement.create`.
        if ($this->periodLocked($date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === ReimbursementSetting::LOCK_BLOCK_ALL
                || ($policy === ReimbursementSetting::LOCK_BLOCK_EMPLOYEE && !$byAdmin)) {

                return [
                    'allowed' => false,
                    'reason'  => 'The reporting period for that date is already locked.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /** @return array{allowed: bool, reason: string} */
    private function checkItemRules(array $items, ReimbursementSetting $settings): array
    {
        if ($items === []) {
            return [
                'allowed' => false,
                'reason'  => 'Add at least one reimbursement item.',
            ];
        }

        if ($settings->hasItemLimit() && count($items) > $settings->max_items_per_request) {
            return [
                'allowed' => false,
                'reason'  => 'A reimbursement can contain at most '
                           . $settings->max_items_per_request . ' items.',
            ];
        }

        if ($this->totals->sum($items) <= 0) {
            return [
                'allowed' => false,
                'reason'  => 'The total amount must be greater than zero.',
            ];
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Aturan yang diteruskan ke ReimbursementTotalService.
     *
     * Dikumpulkan di satu tempat supaya submit, edit, dan impor menilai dokumen
     * dengan aturan yang persis sama.
     */
    private function rulesFrom(ReimbursementSetting $settings, array $payload): array
    {
        return [
            'max_request_amount' => (float) $settings->max_request_amount,
            'min_item_amount'    => (float) $settings->min_item_amount,
            'has_supporting_url' => trim((string) ($payload['supporting_url'] ?? '')) !== '',
        ];
    }

    /**
     * Bersihkan dan urutkan ulang baris item dari form.
     *
     * 🔴 Form mengirim item dengan kunci acak (`items[<uuid>][amount]`), BUKAN
     * indeks berurutan — kalau berurutan, menghapus baris di tengah membuat
     * indeksnya bolong dan baris berikutnya tertimpa. Nomor urut yang dilihat
     * pengguna dibangun di sini, dari urutan kedatangannya.
     *
     * Label cabang DIBEKUKAN di sini juga (Keputusan D105), sekali, saat menulis.
     */
    private function normaliseItems(array $rawItems): array
    {
        $branches = Branch::withTrashed()
            ->get(['id', 'code', 'name'])
            ->keyBy('id');

        $items  = [];
        $lineNo = 1;

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $description = trim((string) ($raw['description'] ?? ''));
            $amount      = (float) ($raw['amount'] ?? 0);

            // Baris yang sepenuhnya kosong dibuang tanpa protes: form
            // multi-baris selalu meninggalkan satu baris kosong di bawah.
            if ($description === '' && $amount <= 0) {
                continue;
            }

            $branchId = isset($raw['branch_id']) && $raw['branch_id'] !== ''
                ? (int) $raw['branch_id']
                : null;

            $branch = $branchId !== null ? $branches->get($branchId) : null;

            $from = trim((string) ($raw['receipt_date_from'] ?? ''));
            $to   = trim((string) ($raw['receipt_date_to'] ?? ''));

            $items[] = [
                'line_no'             => $lineNo++,
                'description'         => $description,
                'cost_center_type'    => ReimbursementItem::COST_CENTER_BRANCH,
                'branch_id'           => $branchId,
                'delivery_project_id' => null,
                'cost_center_label'   => $branch ? $branch->code . ' – ' . $branch->name : null,
                'receipt_no'          => trim((string) ($raw['receipt_no'] ?? '')) ?: null,
                'receipt_date_from'   => $from,
                // Nota satu hari mengisi keduanya dengan tanggal yang sama,
                // bukan membiarkan `to` kosong — dengan begitu penyaringan
                // rentang tanggal tidak butuh cabang khusus.
                'receipt_date_to'     => $to !== '' ? $to : $from,
                'currency'            => 'IDR',
                'amount'              => max(0.0, round($amount, 2)),
            ];
        }

        return $items;
    }

    private function writeItems(ReimbursementRequest $request, array $items): void
    {
        foreach ($items as $item) {
            $request->items()->create($item);
        }
    }

    /** Salin definisi langkah ke dokumen — lihat docblock migrasinya. */
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
        return ReimbursementRequest::query()
            ->whereIn('status', ReimbursementRequest::OPEN_STATUSES)
            ->where('current_step_order', '<', $orderSeq)
            ->count();
    }

    /**
     * Terapkan satu langkah persetujuan BARU ke dokumen yang sedang berjalan.
     *
     * 🔴 ATURANNYA ASIMETRIS, DAN ITU DISENGAJA.
     *
     * Menambah langkah berarti MEMPERKETAT kontrol. Memperketat tidak pernah
     * merusak persetujuan yang sudah terjadi, dan justru dokumen yang sedang
     * berjalan-lah yang paling perlu diamankan — orang menambah penyetuju
     * biasanya karena baru menyadari ada celah, bukan untuk bulan depan.
     *
     * Sebaliknya MENGHAPUS atau MELONGGARKAN langkah TIDAK PERNAH berlaku
     * surut. Di sanalah bahayanya: dokumen yang menunggu di langkah yang dihapus
     * bisa melompat jadi disetujui tanpa ditinjau siapa pun, atau persetujuan
     * yang sudah terjadi kehilangan maknanya. Itu sebabnya salinan langkah per
     * dokumen tetap dipertahankan.
     *
     * Batas yang dijaga:
     *  - HANYA dokumen berstatus terbuka; yang sudah approved/rejected tidak
     *    tersentuh sama sekali
     *  - HANYA bila langkah barunya berada SESUDAH langkah yang sedang menunggu.
     *    Langkah yang posisinya sudah dilewati dokumen itu diabaikan — dokumennya
     *    memang sudah sampai di sana
     *  - Baris riwayat yang sudah bertindak TIDAK PERNAH ditulis ulang
     *  - Dokumen terhapus dilewati
     *
     * @return array{applied: int, request_nos: array<string>}
     */
    public function applyStepToOpenRequests(ReimbursementApprovalStep $step, ?int $actorId = null): array
    {
        $targets = ReimbursementRequest::query()
            ->whereIn('status', ReimbursementRequest::OPEN_STATUSES)
            ->where('current_step_order', '<', $step->order_seq)
            ->get();

        if ($targets->isEmpty()) {
            return ['applied' => 0, 'request_nos' => []];
        }

        $applied = [];

        DB::transaction(function () use ($targets, $step, &$applied) {
            foreach ($targets as $request) {
                // Jaring pengaman: jangan pernah menggandakan urutan yang sudah
                // ada pada dokumen itu.
                $exists = $request->approvals()
                    ->where('order_seq', $step->order_seq)
                    ->exists();

                if ($exists) {
                    continue;
                }

                ReimbursementRequestApproval::create([
                    'reimbursement_request_id' => $request->id,
                    'order_seq'                => $step->order_seq,
                    'step_name'                => $step->name,
                    'approver_type'            => $step->approver_type,
                    'approver_role_id'         => $step->approver_role_id,
                    'approver_employee_ids'    => $step->approver_employee_ids,
                    'status'                   => ReimbursementRequestApproval::STATUS_WAITING,
                ]);

                $this->addFlag($request, ReimbursementRequest::FLAG_WORKFLOW_EXTENDED);

                $applied[] = $request->request_no;
            }
        });

        if ($applied !== []) {
            // Dicatat supaya pertanyaan "kenapa dokumen ini butuh satu tanda
            // tangan lagi?" dapat ditelusuri ke tindakan ini, lengkap dengan
            // pelakunya (pola Keputusan D53).
            Log::info('Reimbursement approval step applied to in-progress documents.', [
                'actor_id'    => $actorId,
                'step_name'   => $step->name,
                'order_seq'   => $step->order_seq,
                'applied'     => count($applied),
                'request_nos' => $applied,
            ]);
        }

        return ['applied' => count($applied), 'request_nos' => $applied];
    }

    private function snapshotSteps(ReimbursementRequest $request, Collection $steps): void
    {
        foreach ($steps as $step) {
            ReimbursementRequestApproval::create([
                'reimbursement_request_id' => $request->id,
                'order_seq'                => $step->order_seq,
                'step_name'                => $step->name,
                'approver_type'            => $step->approver_type,
                'approver_role_id'         => $step->approver_role_id,
                'approver_employee_ids'    => $step->approver_employee_ids,
                'status'                   => ReimbursementRequestApproval::STATUS_WAITING,
            ]);
        }
    }

    /**
     * Nomor dokumen berikutnya: RB/2026/08/00001.
     *
     * Dikunci di dalam transaksi supaya dua pengajuan bersamaan tidak mendapat
     * nomor yang sama. Indeks unik pada kolomnya menjadi jaring pengaman terakhir
     * bila penguncian gagal karena sebab di luar dugaan (Keputusan D92).
     *
     * `withTrashed()` disengaja: nomor dokumen yang sudah dihapus TIDAK boleh
     * dipakai ulang, kalau tidak dua dokumen berbeda akan memakai nomor yang sama
     * di mata auditor.
     */
    private function nextRequestNo(Carbon $date): string
    {
        $prefix = sprintf('RB/%s/%s/', $date->format('Y'), $date->format('m'));

        $last = ReimbursementRequest::withTrashed()
            ->where('request_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('request_no')
            ->value('request_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function addFlag(ReimbursementRequest $request, string $flag): void
    {
        $flags = $request->flags ?? [];

        if (!in_array($flag, $flags, true)) {
            $flags[] = $flag;
            $request->update(['flags' => array_values($flags)]);
        }
    }

    /** @return array<int> */
    private function roleIdsOf(int $employeeId): array
    {
        return Employee::where('employee_id', $employeeId)->first()?->getRoleIds() ?? [];
    }

    /**
     * Beri tahu karyawan perkembangan dokumennya.
     *
     * Dibungkus try/catch karena kegagalan mengirim notifikasi tidak boleh
     * membatalkan persetujuan yang sudah tersimpan — tetapi tetap dicatat ke log
     * supaya kegagalannya tidak hilang diam-diam (Keputusan D44).
     */
    private function notify(ReimbursementRequest $request, string $outcome, ?string $notes = null): void
    {
        try {
            $amount = ReimbursementRequest::formatRupiah($request->total_amount);

            $message = match ($outcome) {
                'approved'   => "Your reimbursement {$request->request_no} ({$amount}) was approved.",
                'rejected'   => "Your reimbursement {$request->request_no} ({$amount}) was rejected.",
                'progressed' => "Your reimbursement {$request->request_no} passed a review step.",
                default      => "Your reimbursement {$request->request_no} was updated.",
            };

            Notification::create([
                'employee_id'      => $request->employee_id,
                'type'             => 'reimbursement_' . $outcome,
                'from_employee_id' => session('user.id'),
                'preview'          => $message . ($notes ? ' Note: ' . $notes : ''),
                'link'             => '/general/my-reimbursement',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send reimbursement notification.', [
                'request_id' => $request->id,
                'outcome'    => $outcome,
                'message'    => $e->getMessage(),
            ]);
        }
    }
}
