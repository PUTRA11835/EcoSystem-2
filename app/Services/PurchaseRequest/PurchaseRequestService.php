<?php

namespace App\Services\PurchaseRequest;

use App\Models\Attendance\Branch;
use App\Models\DeliveryProject;
use App\Models\Employee;
use App\Models\EmployeeRole;
use App\Models\Notification;
use App\Models\PurchaseRequest\PurchaseRequest;
use App\Models\PurchaseRequest\PurchaseRequestApproval;
use App\Models\PurchaseRequest\PurchaseRequestApprovalStep;
use App\Models\PurchaseRequest\PurchaseRequestItem;
use App\Models\PurchaseRequest\PurchaseRequestSetting;
use App\Models\ReportingPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Mesin pengajuan dan persetujuan Purchase Request.
 *
 * AGNOSTIK TERHADAP TRANSPORT: menerima array biasa, bukan Request, sehingga bila
 * kelak endpoint mobile atau konversi ke Purchase Order dibuka, cukup menambah
 * controller tipis tanpa menyentuh aturan bisnisnya. Pola yang sama dipakai
 * AttendanceService, OvertimeService, dan ReimbursementService.
 *
 * Seluruh gerbang bisnis mengembalikan ['allowed' => bool, 'reason' => string]
 * meniru PeriodService yang sudah jadi konvensi di basis kode ini.
 *
 * 🔴 `employee_id` SELALU diterima sebagai argumen dari pemanggil yang
 * mengambilnya dari sesi — tidak pernah dari badan request. Ini yang mencegah
 * seseorang mengajukan atas nama rekannya. Satu-satunya pengecualian adalah
 * pembuatan oleh admin ("New PR"), yang menyebut karyawannya secara eksplisit
 * DAN mencatat pembuatnya di `created_by`.
 *
 * PEMBAGIAN TUGAS dengan PurchaseRequestSummaryService: yang murni meringkas dan
 * MENANDAI ada di sana; yang MENOLAK ada di sini, karena penolakan selalu
 * menyangkut keadaan di luar item — periode terkunci, langkah persetujuan, dan
 * setelan yang menuntut kelengkapan.
 */
class PurchaseRequestService
{
    public function __construct(private PurchaseRequestSummaryService $summary)
    {
    }

    // =======================================================================
    // PENGAJUAN
    // =======================================================================

    /**
     * Ajukan purchase request.
     *
     * @param  array{request_date: string, title: string, notes: ?string, items: array, approver_id: ?int}  $payload
     * @param  int|null  $createdBy  Diisi HANYA bila admin membuat atas nama karyawan.
     * @return array{allowed: bool, reason: string, request: ?PurchaseRequest}
     */
    public function submit(int $employeeId, array $payload, ?int $createdBy = null): array
    {
        $settings = PurchaseRequestSetting::current();
        $date     = Carbon::parse($payload['request_date'])->startOfDay();

        $gate = $this->checkDateRules($date, $settings, $createdBy !== null);
        if (!$gate['allowed']) {
            return $gate + ['request' => null];
        }

        $gate = $this->checkRawItems($payload['items'] ?? []);
        if (!$gate['allowed']) {
            return $gate + ['request' => null];
        }

        $items = $this->prepareItems($payload['items'] ?? [], $settings);

        $gate = $this->checkItemRules($items, $settings);
        if (!$gate['allowed']) {
            return $gate + ['request' => null];
        }

        // Langkah persetujuan harus ada SEBELUM dokumen dibuat. Tanpa satu pun
        // langkah aktif, dokumen akan lahir tanpa jalan keluar — tidak dapat
        // disetujui maupun ditolak siapa pun.
        $steps = $this->activeSteps();
        if ($steps->isEmpty()) {
            return [
                'allowed' => false,
                'reason'  => 'No approval step is configured yet. Ask HR to set one up in Purchase Request Settings.',
                'request' => null,
            ];
        }

        // Penyetuju yang dipilih pemohon (Keputusan D126). Divalidasi terhadap
        // daftar kandidat langkahnya — pilihan di luar daftar ditolak, kalau
        // tidak siapa pun dapat menunjuk penyetuju sesukanya dengan menyunting
        // HTML-nya.
        $gate = $this->resolveChosenApprovers($steps, $payload);
        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason'], 'request' => null];
        }
        $chosen = $gate['chosen'];

        $evaluated = $this->summary->evaluate($items, $this->rulesFrom($settings));

        $flags = $evaluated['flags'];
        if ($createdBy !== null && $createdBy !== $employeeId) {
            $flags[] = PurchaseRequest::FLAG_CREATED_ON_BEHALF;
        }

        $request = DB::transaction(function () use (
            $employeeId, $createdBy, $date, $payload, $items, $evaluated, $flags, $steps, $chosen
        ) {
            $request = PurchaseRequest::create([
                'request_no'         => $this->nextRequestNo($date),
                'employee_id'        => $employeeId,
                'created_by'         => $createdBy,
                'request_date'       => $date->toDateString(),
                'title'              => $payload['title'],
                'notes'              => $payload['notes'] ?? null,
                'cost_center_type'   => $evaluated['cost_center_type'],
                'charged_branch_id'  => $evaluated['charged_branch_id'],
                'charged_project_id' => $evaluated['charged_project_id'],
                'charged_to_label'   => $evaluated['charged_to_label'],
                'item_count'         => $evaluated['item_count'],
                'qty_summary'        => $evaluated['qty_summary'],
                'status'             => PurchaseRequest::STATUS_SUBMITTED,
                'current_step_order' => $steps->first()->order_seq,
                'flags'              => array_values(array_unique($flags)),
                'period_year'        => (int) $date->format('Y'),
                'period_month'       => (int) $date->format('n'),
            ]);

            $this->writeItems($request, $items);
            $this->snapshotSteps($request, $steps, $chosen);

            return $request;
        });

        return [
            'allowed' => true,
            'reason'  => '',
            'request' => $request->fresh(['items', 'approvals']),
        ];
    }

    /**
     * Ubah dokumen yang masih berjalan.
     *
     * Item ditulis ulang seluruhnya, bukan ditambal satu-satu: form mengirim
     * keadaan akhir yang dikehendaki, dan mencocokkan baris lama dengan baris
     * baru satu per satu hanya menambah jalan bagi ringkasan menyimpang dari
     * isinya.
     *
     * 🔴 Alur persetujuan TIDAK ikut ditulis ulang. Mengubah isi dokumen tidak
     * boleh mengulang persetujuan yang sudah terjadi maupun mengganti penyetuju
     * yang sedang menunggu.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function update(PurchaseRequest $request, array $payload, int $actorId): array
    {
        if (!$request->isEditable()) {
            return [
                'allowed' => false,
                'reason'  => 'This document is already closed and can no longer be edited.',
            ];
        }

        $settings = PurchaseRequestSetting::current();

        $gate = $this->checkRawItems($payload['items'] ?? []);
        if (!$gate['allowed']) {
            return $gate;
        }

        $items = $this->prepareItems($payload['items'] ?? [], $settings);

        $gate = $this->checkItemRules($items, $settings);
        if (!$gate['allowed']) {
            return $gate;
        }

        $evaluated = $this->summary->evaluate($items, $this->rulesFrom($settings));

        $before = [
            'item_count'  => (int) $request->item_count,
            'qty_summary' => (string) $request->qty_summary,
        ];

        $changed = $before['item_count'] !== $evaluated['item_count']
                || $before['qty_summary'] !== $evaluated['qty_summary'];

        DB::transaction(function () use ($request, $payload, $items, $evaluated, $changed) {
            $flags = $evaluated['flags'];

            // Flag netral yang tidak lahir dari item dipertahankan: ia mencatat
            // fakta historis, bukan keadaan isi dokumen saat ini.
            foreach ([
                PurchaseRequest::FLAG_CREATED_ON_BEHALF,
                PurchaseRequest::FLAG_SELF_APPROVED,
                PurchaseRequest::FLAG_LOCKED_PERIOD,
                PurchaseRequest::FLAG_WORKFLOW_EXTENDED,
            ] as $sticky) {
                if ($request->hasFlag($sticky)) {
                    $flags[] = $sticky;
                }
            }

            if ($changed) {
                $flags[] = PurchaseRequest::FLAG_ITEMS_ADJUSTED;
            }

            $request->update([
                'title'              => $payload['title'] ?? $request->title,
                'notes'              => $payload['notes'] ?? null,
                'cost_center_type'   => $evaluated['cost_center_type'],
                'charged_branch_id'  => $evaluated['charged_branch_id'],
                'charged_project_id' => $evaluated['charged_project_id'],
                'charged_to_label'   => $evaluated['charged_to_label'],
                'item_count'         => $evaluated['item_count'],
                'qty_summary'        => $evaluated['qty_summary'],
                'flags'              => array_values(array_unique($flags)),
            ]);

            $request->items()->delete();
            $this->writeItems($request, $items);
        });

        // Perubahan isi dokumen pengadaan harus dapat ditelusuri di luar
        // tabelnya sendiri (pola Keputusan D53).
        if ($changed) {
            Log::info('Purchase request items changed after submission.', [
                'request_no' => $request->request_no,
                'actor_id'   => $actorId,
                'before'     => $before,
                'after'      => [
                    'item_count'  => $evaluated['item_count'],
                    'qty_summary' => $evaluated['qty_summary'],
                ],
            ]);
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Tarik kembali dokumen oleh PEMOHONNYA sendiri (Keputusan D131).
     *
     * 🔴 Ini yang tidak ada di Reimbursement, dan penyimpangannya disengaja:
     * D111 melarang pembatalan karena reimbursement yang disetujui adalah dasar
     * PEMBAYARAN. Purchase Request belum menimbulkan komitmen uang, jadi sifat
     * itu tidak berlaku.
     *
     * Batasnya bukan sopan santun: hanya boleh selama status masih `submitted`,
     * yaitu selama belum ada satu pun langkah yang bertindak. Penyetuju yang
     * sudah meluangkan waktu meninjau tidak boleh kehilangan pekerjaannya karena
     * pemohon berubah pikiran.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function cancel(PurchaseRequest $request, int $actorId): array
    {
        $settings = PurchaseRequestSetting::current();

        if (!$settings->allow_requester_cancel) {
            return [
                'allowed' => false,
                'reason'  => 'Cancelling your own request is disabled in Purchase Request Settings.',
            ];
        }

        if ($actorId !== (int) $request->employee_id) {
            return [
                'allowed' => false,
                'reason'  => 'Only the requester can cancel this document.',
            ];
        }

        if (!$request->isCancellable()) {
            return [
                'allowed' => false,
                'reason'  => $request->isOpen()
                    ? 'This request is already being reviewed and can no longer be cancelled.'
                    : 'This request is already ' . $request->status . '.',
            ];
        }

        DB::transaction(function () use ($request, $actorId) {
            // Langkah yang belum dijalani ditandai `skipped`, bukan dibiarkan
            // `waiting`: dibiarkan menunggu, ia akan tampak seolah masih ada yang
            // harus bertindak pada dokumen yang sudah tertutup.
            $request->approvals()
                ->where('status', PurchaseRequestApproval::STATUS_WAITING)
                ->update(['status' => PurchaseRequestApproval::STATUS_SKIPPED]);

            $request->update([
                'status'             => PurchaseRequest::STATUS_CANCELLED,
                'current_step_order' => null,
                'cancelled_at'       => now(),
                'cancelled_by'       => $actorId,
                'completed_at'       => now(),
            ]);
        });

        Log::info('Purchase request cancelled by its requester.', [
            'request_no'  => $request->request_no,
            'employee_id' => $request->employee_id,
        ]);

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Hapus dokumen — SOFT DELETE beserta alasannya (Keputusan D109).
     *
     * Aplikasi acuan menghapusnya permanen; itu sengaja tidak diikuti. Dokumen
     * yang menjadi dasar pengadaan tidak boleh lenyap tanpa jejak: bila barisnya
     * hilang, tidak ada yang dapat menjawab dokumen mana yang hilang, siapa yang
     * menghapusnya, dan atas dasar apa.
     *
     * @return array{allowed: bool, reason: string}
     */
    public function softDelete(PurchaseRequest $request, int $actorId, string $reason): array
    {
        $reason = trim($reason);

        if ($reason === '') {
            return [
                'allowed' => false,
                'reason'  => 'Please state why this document is being deleted.',
            ];
        }

        $wasApproved = $request->status === PurchaseRequest::STATUS_APPROVED;

        DB::transaction(function () use ($request, $actorId, $reason) {
            $request->update([
                'deleted_by'    => $actorId,
                'delete_reason' => $reason,
            ]);

            $request->delete();
        });

        // Menghapus dokumen yang SUDAH disetujui berarti menghapus dasar
        // pengadaan yang mungkin sudah berjalan. Tetap diizinkan — pemegang
        // `.manage` yang bertanggung jawab — tetapi dicatat menonjol supaya
        // pertanyaan auditor selalu ada jawabannya.
        if ($wasApproved) {
            Log::warning('An APPROVED purchase request was deleted.', [
                'request_no'  => $request->request_no,
                'employee_id' => $request->employee_id,
                'items'       => (int) $request->item_count,
                'qty_summary' => $request->qty_summary,
                'actor_id'    => $actorId,
                'reason'      => $reason,
            ]);
        } else {
            Log::info('Purchase request deleted.', [
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
     * Memeriksa LIMA hal terpisah dengan pesan galat yang berbeda-beda, supaya
     * penyetuju tahu apa yang salah alih-alih menerima satu penolakan buram.
     *
     * @return array{allowed: bool, reason: string, approval: ?PurchaseRequestApproval}
     */
    public function canAct(PurchaseRequest $request, int $actorId, bool $canManage = false): array
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

        $settings = PurchaseRequestSetting::current();

        if ($this->periodLocked($request->request_date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === PurchaseRequestSetting::LOCK_BLOCK_ALL) {
                return [
                    'allowed'  => false,
                    'reason'   => 'The reporting period for this document is locked, and the current policy blocks everyone.',
                    'approval' => null,
                ];
            }

            if ($policy === PurchaseRequestSetting::LOCK_BLOCK_EMPLOYEE && !$canManage) {
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
                'reason'   => 'You cannot approve your own purchase request. Self-approval is disabled in Purchase Request Settings.'
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
    public function approve(PurchaseRequest $request, int $actorId, array $payload = [], bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);

        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason'], 'completed' => false];
        }

        /** @var PurchaseRequestApproval $approval */
        $approval = $gate['approval'];

        $completed = DB::transaction(function () use ($request, $approval, $actorId, $payload) {
            if ($actorId === (int) $request->employee_id) {
                $this->addFlag($request, PurchaseRequest::FLAG_SELF_APPROVED);
            }

            if ($this->periodLocked($request->request_date)) {
                $this->addFlag($request, PurchaseRequest::FLAG_LOCKED_PERIOD);
            }

            $approval->update([
                'status'   => PurchaseRequestApproval::STATUS_APPROVED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $payload['notes'] ?? null,
            ]);

            $next = $request->approvals()
                ->where('order_seq', '>', $approval->order_seq)
                ->where('status', PurchaseRequestApproval::STATUS_WAITING)
                ->orderBy('order_seq')
                ->first();

            if ($next) {
                $request->update([
                    'status'             => PurchaseRequest::STATUS_IN_REVIEW,
                    'current_step_order' => $next->order_seq,
                ]);

                return false;
            }

            $request->update([
                'status'             => PurchaseRequest::STATUS_APPROVED,
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
    public function reject(PurchaseRequest $request, int $actorId, string $notes, bool $canManage = false): array
    {
        $gate = $this->canAct($request, $actorId, $canManage);

        if (!$gate['allowed']) {
            return ['allowed' => false, 'reason' => $gate['reason']];
        }

        /** @var PurchaseRequestApproval $approval */
        $approval = $gate['approval'];

        DB::transaction(function () use ($request, $approval, $actorId, $notes) {
            $approval->update([
                'status'   => PurchaseRequestApproval::STATUS_REJECTED,
                'acted_by' => $actorId,
                'acted_at' => now(),
                'notes'    => $notes,
            ]);

            $request->approvals()
                ->where('order_seq', '>', $approval->order_seq)
                ->where('status', PurchaseRequestApproval::STATUS_WAITING)
                ->update(['status' => PurchaseRequestApproval::STATUS_SKIPPED]);

            $request->update([
                'status'             => PurchaseRequest::STATUS_REJECTED,
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
        return PurchaseRequest::forEmployee($employeeId)
            ->with(['items', 'approvals.actor.basicData', 'approvals.role'])
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    /**
     * Ringkasan bulan berjalan untuk kartu di halaman karyawan.
     *
     * Hanya JUMLAH DOKUMEN — dokumen ini memang tidak punya nominal, jadi tidak
     * ada angka lain yang pantas diringkas di sini. Kuantitas per dokumen tetap
     * terbaca di kolom Qty pada tabelnya, dan menjumlahkan kuantitas lintas
     * dokumen tidak berarti apa-apa: "20 PC + 5 SET" bukan satu angka.
     *
     * `cancelled` dihitung terpisah, bukan digabung ke `submitted`: pemohon perlu
     * melihat berapa yang ia tarik kembali sendiri (D131).
     *
     * @return array{submitted: int, approved: int, pending: int, cancelled: int}
     */
    public function monthlySummary(int $employeeId, int $year, int $month): array
    {
        $rows = PurchaseRequest::forEmployee($employeeId)
            ->forPeriod($year, $month)
            ->selectRaw('status, COUNT(*) AS total')
            ->groupBy('status')
            ->get()
            ->keyBy('status');

        $count = fn (string $status) => (int) ($rows[$status]->total ?? 0);

        return [
            'submitted' => (int) $rows->sum('total'),
            'approved'  => $count(PurchaseRequest::STATUS_APPROVED),
            'pending'   => collect(PurchaseRequest::OPEN_STATUSES)->sum($count),
            'cancelled' => $count(PurchaseRequest::STATUS_CANCELLED),
        ];
    }

    /**
     * Id dokumen yang sedang menunggu tindakan orang ini.
     *
     * Dipakai menandai baris di rekap dan menghitung lencana. Disaring di PHP,
     * bukan di SQL, karena kelayakan bertindak bergantung pada `allows()` yang
     * membaca JSON dan daftar role — dua hal yang tidak dapat diungkapkan sebagai
     * satu klausa WHERE tanpa menduplikasi aturannya di dua tempat.
     *
     * @return array<int>
     */
    public function pendingIdsFor(int $actorId): array
    {
        $roleIds = $this->roleIdsOf($actorId);

        return PurchaseRequest::open()
            ->with('approvals')
            ->get()
            ->filter(function (PurchaseRequest $request) use ($actorId, $roleIds) {
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
        return PurchaseRequestApprovalStep::forPurchaseRequest()
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
     * divalidasi di TIGA tempat: pengajuan mandiri, form "New PR" milik admin,
     * dan Edit. Aturan yang disalin akan menyimpang cepat atau lambat.
     *
     * 🔴 `unit` divalidasi terhadap `allowed_units` dari Settings, BUKAN daftar
     * yang dipatok di kode. Kalau dipatok, setelan CSV-nya menjadi setelan mati —
     * pemilik sistem mengubahnya dan tidak terjadi apa-apa (kegagalan D52).
     *
     * 🔴 Urutan periode TIDAK dapat diperiksa dengan
     * `after_or_equal:items.*.period_from` — Laravel tidak menyelesaikan jalur
     * berwildcard untuk aturan pembanding field, dan mencoba mengurai teks
     * "items.*.period_from" itu sendiri sebagai tanggal. Karena itu dipakai
     * closure yang mencari field sebelahnya lewat jalur atributnya.
     *
     * @param  array  $input  Seluruh masukan, dipakai closure mencari field sebelah.
     * @return array<string, array>
     */
    public function itemRules(PurchaseRequestSetting $settings, array $input): array
    {
        $required = $settings->require_cost_center_per_item;

        return [
            'items'                       => ['required', 'array', 'min:1'],
            'items.*.description'         => ['required', 'string', 'max:200'],
            'items.*.qty'                 => ['required', 'numeric', 'min:0.01'],
            'items.*.unit'                => ['required', 'string', 'in:' . implode(',', $settings->unitOptions())],
            'items.*.cost_center_type'    => [$required ? 'required' : 'nullable', 'string',
                                              'in:' . implode(',', $settings->costCenterTypeOptions())],
            'items.*.branch_id'           => ['nullable', 'integer', 'exists:branches,id'],
            'items.*.delivery_project_id' => ['nullable', 'integer', 'exists:delivery_projects,id'],
            'items.*.period_from'         => [$settings->require_period ? 'required' : 'nullable', 'date'],
            'items.*.period_to'           => ['nullable', 'date', $this->dateOrderRule($input)],
            'items.*.use_date'            => [$settings->require_use_date ? 'required' : 'nullable', 'date'],
        ];
    }

    /** Akhir periode tidak boleh mendahului awalnya. */
    private function dateOrderRule(array $input): \Closure
    {
        return function (string $attribute, $value, \Closure $fail) use ($input) {
            if (!$value) {
                return;
            }

            $from = data_get($input, str_replace('period_to', 'period_from', $attribute));

            if ($from && strtotime((string) $value) < strtotime((string) $from)) {
                $fail('The period end date cannot be earlier than its start date.');
            }
        };
    }

    /**
     * Kolom tanda tangan pada cetakan dan Excel (Keputusan D129).
     *
     * 🔴 DITURUNKAN DARI LANGKAH ALUR, bukan dari setelan — ini yang membedakan
     * Purchase Request dari Reimbursement. Di sana kolom Accounting & Kasir
     * memang bukan bagian alur persetujuan sehingga harus disimpan di Settings.
     * Di sini setiap penanda tangan ADALAH penyetuju, jadi menyimpannya terpisah
     * hanya menciptakan satu kelas kesalahan baru: setelan berkata A, riwayat
     * persetujuan berkata B, dan tidak ada yang tahu mana yang benar.
     *
     * Akibatnya jumlah kolom mengikuti jumlah langkah:
     *   1 langkah  -> Requester + Verification            = 2 kolom
     *   2 langkah  -> Requester + 2 nama langkah          = 3 kolom
     *
     * Barisnya dibaca dari `purchase_request_approvals` — SALINAN milik dokumen
     * itu. Karena itu cetak ulang dokumen lama setelah alur diubah tetap
     * menghasilkan kertas yang sama.
     *
     * @return array<int, array{title: string, name: string, pending: bool}>
     */
    public function signatureColumns(PurchaseRequest $request): array
    {
        $columns = [[
            'title'   => 'Requester',
            'name'    => $this->nameOf($request->employee),
            'pending' => false,
        ]];

        foreach ($request->approvals as $approval) {
            if ($approval->status === PurchaseRequestApproval::STATUS_SKIPPED) {
                continue;
            }

            if ($approval->acted_by) {
                $columns[] = [
                    'title'   => $approval->step_name,
                    'name'    => $this->nameOf($approval->actor),
                    'pending' => false,
                ];

                continue;
            }

            // Belum bertindak. Bila kandidatnya tunggal, namanya tetap dicetak
            // (abu, lewat flag `pending`) supaya pembaca dokumen tahu siapa yang
            // sedang ditunggu. Bila kandidatnya banyak, kolomnya dibiarkan kosong
            // — menebak satu nama dari beberapa akan mencetak orang yang salah.
            $ids  = array_map('intval', $approval->approver_employee_ids ?? []);
            $name = count($ids) === 1
                ? $this->nameOf(Employee::with('basicData')->find($ids[0]))
                : '';

            $columns[] = [
                'title'   => $approval->step_name,
                'name'    => $name,
                'pending' => true,
            ];
        }

        return $columns;
    }

    /**
     * Baris identitas perusahaan di kepala cetakan dan Excel (Keputusan D113).
     *
     * Memakai nama cabang bila pengaturannya menghendaki DAN seluruh item berada
     * di satu cabang. Dokumen lintas pembebanan selalu jatuh ke nama perusahaan.
     *
     * 🔴 Dokumen yang seluruh itemnya di satu PROYEK juga jatuh ke nama
     * perusahaan: nama proyek adalah pihak yang dibebani, bukan pihak yang
     * menerbitkan dokumen.
     */
    public function documentHeading(PurchaseRequest $request): string
    {
        $settings = PurchaseRequestSetting::current();

        if ($settings->use_branch_name_in_header
            && $request->charged_branch_id
            && $request->chargedBranch) {

            return $request->chargedBranch->name;
        }

        return $settings->company_name;
    }

    /**
     * Pilihan pembebanan untuk dropdown item, beserta label yang siap dibekukan.
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
        $settings = PurchaseRequestSetting::current();
        $allowed  = $settings->costCenterTypeOptions();

        $branches = in_array(PurchaseRequestItem::COST_CENTER_BRANCH, $allowed, true)
            ? Branch::active()
                ->orderBy('name')
                ->get(['id', 'code', 'name'])
                ->map(fn (Branch $branch) => [
                    'id'    => (int) $branch->id,
                    'label' => $this->branchLabel($branch),
                ])
                ->all()
            : [];

        $projects = in_array(PurchaseRequestItem::COST_CENTER_PROJECT, $allowed, true)
            ? DeliveryProject::where('is_closed', 0)
                ->orderBy('name')
                ->get(['id', 'io_number', 'name'])
                ->map(fn (DeliveryProject $project) => [
                    'id'    => (int) $project->id,
                    'label' => $this->projectLabel($project),
                ])
                ->all()
            : [];

        return ['branch' => $branches, 'project' => $projects];
    }

    // =======================================================================
    // internal
    // =======================================================================

    /** @return array{allowed: bool, reason: string} */
    private function checkDateRules(Carbon $date, PurchaseRequestSetting $settings, bool $byAdmin): array
    {
        // 🔴 Bawaannya TRUE di modul ini, kebalikan dari Reimbursement: PR
        // meminta barang yang belum dibeli, jadi tanggal masa depan justru
        // pemakaian yang paling wajar.
        if (!$settings->allow_future_date && $date->greaterThan(now()->startOfDay())) {
            return [
                'allowed' => false,
                'reason'  => 'A purchase request cannot be submitted for a future date.',
            ];
        }

        if ($settings->hasBackdateLimit()
            && $date->lessThan(now()->startOfDay()->subDays($settings->max_backdate_days))) {

            return [
                'allowed' => false,
                'reason'  => 'A purchase request can only be submitted for the last '
                           . $settings->max_backdate_days . ' days.',
            ];
        }

        // Periode terkunci: karyawan tertahan, admin pemegang hak buat tidak.
        // `byAdmin` di sini berarti dokumen dibuat lewat "New PR", yang hanya
        // dapat diakses pemegang `general.purchase-request.create`.
        if ($this->periodLocked($date)) {
            $policy = $settings->locked_period_policy;

            if ($policy === PurchaseRequestSetting::LOCK_BLOCK_ALL
                || ($policy === PurchaseRequestSetting::LOCK_BLOCK_EMPLOYEE && !$byAdmin)) {

                return [
                    'allowed' => false,
                    'reason'  => 'The reporting period for that date is already locked.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Periksa baris MENTAH — sebelum dinormalkan.
     *
     * 🔴 Kenapa ada pemeriksaan terpisah sebelum normalisasi, padahal
     * `normaliseItems()` sudah memaksa kolom yang tidak sesuai tipenya jadi null:
     *
     * Karena keduanya menjawab pertanyaan yang berbeda. Normalisasi menangani
     * kasus yang WAJAR — pengguna berpindah dari Branch ke Project setelah
     * terlanjur memilih cabang, sehingga form membawa nilai lama. Di situ
     * `cost_center_type` yang dikirim adalah maksud pengguna, dan memakainya
     * sebagai penentu adalah perilaku yang benar.
     *
     * Yang TIDAK boleh diperlakukan begitu adalah baris yang membawa DUA
     * pembebanan TANPA menyebut tipenya. Di sana tidak ada maksud yang dapat
     * dibaca — dan diam-diam memilih salah satu dari dua instruksi yang
     * bertentangan persis hal yang diperingatkan docblock migrasi item: tidak ada
     * yang tahu mana yang berlaku saat pengadaan berjalan. Itu ditolak, dengan
     * pesan yang menyebutkan barisnya.
     *
     * Tanpa pemeriksaan ini, `assertSingleCostCenter()` tidak akan pernah menyala
     * di jalur form — ia hanya menerima baris yang sudah dinormalkan, yang menurut
     * definisi tidak mungkin membawa dua pembebanan. Penjagaan yang tidak dapat
     * menyala bukan penjagaan.
     *
     * @return array{allowed: bool, reason: string}
     */
    private function checkRawItems(array $rawItems): array
    {
        $lineNo = 0;

        foreach ($rawItems as $raw) {
            if (!is_array($raw)) {
                continue;
            }

            $lineNo++;

            $type = strtolower(trim((string) ($raw['cost_center_type'] ?? '')));

            // Tipe yang jelas = maksud yang jelas. Normalisasi yang menanganinya.
            if (in_array($type, PurchaseRequestItem::COST_CENTER_TYPES, true)) {
                continue;
            }

            $hasBranch  = ($raw['branch_id'] ?? '') !== '' && ($raw['branch_id'] ?? null) !== null;
            $hasProject = ($raw['delivery_project_id'] ?? '') !== '' && ($raw['delivery_project_id'] ?? null) !== null;

            if ($hasBranch && $hasProject) {
                return [
                    'allowed' => false,
                    'reason'  => 'Item ' . $lineNo . ' carries both a branch and a project '
                               . 'without stating which one it is charged to.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /** @return array{allowed: bool, reason: string} */
    private function checkItemRules(array $items, PurchaseRequestSetting $settings): array
    {
        if ($items === []) {
            return [
                'allowed' => false,
                'reason'  => 'Add at least one request item.',
            ];
        }

        if ($settings->hasItemLimit() && count($items) > $settings->max_items_per_request) {
            return [
                'allowed' => false,
                'reason'  => 'A purchase request can contain at most '
                           . $settings->max_items_per_request . ' items.',
            ];
        }

        $units = $settings->unitOptions();
        $max   = (float) $settings->max_qty_per_item;

        foreach ($items as $item) {
            if ((float) $item['qty'] <= 0) {
                return [
                    'allowed' => false,
                    'reason'  => 'Item "' . $item['description'] . '" needs a quantity greater than zero.',
                ];
            }

            if (!in_array($item['unit'], $units, true)) {
                return [
                    'allowed' => false,
                    'reason'  => 'Unit "' . $item['unit'] . '" is not allowed. Allowed units: '
                               . implode(', ', $units) . '.',
                ];
            }

            if ($settings->hasQtyLimit() && (float) $item['qty'] > $max) {
                return [
                    'allowed' => false,
                    'reason'  => 'Item "' . $item['description'] . '" exceeds the maximum quantity of '
                               . PurchaseRequestItem::formatQty($max) . ' per item.',
                ];
            }

            if (!$settings->allowsCostCenterType($item['cost_center_type'])) {
                return [
                    'allowed' => false,
                    'reason'  => 'Charging an item to a ' . $item['cost_center_type']
                               . ' is not allowed by the current settings.',
                ];
            }

            $single = $this->summary->assertSingleCostCenter($item);
            if (!$single['ok']) {
                return ['allowed' => false, 'reason' => $single['reason']];
            }

            if ($settings->require_cost_center_per_item
                && $item['branch_id'] === null
                && $item['delivery_project_id'] === null) {

                return [
                    'allowed' => false,
                    'reason'  => 'Item "' . $item['description'] . '" needs a branch or a project.',
                ];
            }
        }

        return ['allowed' => true, 'reason' => ''];
    }

    /**
     * Aturan yang diteruskan ke PurchaseRequestSummaryService.
     *
     * Dikumpulkan di satu tempat supaya submit, "New PR", dan edit menilai
     * dokumen dengan aturan yang persis sama.
     */
    private function rulesFrom(PurchaseRequestSetting $settings): array
    {
        return [
            'unit_order' => $settings->unitOptions(),
            'today'      => now()->toDateString(),
        ];
    }

    /**
     * Normalkan baris item, lalu BEKUKAN label pembebanannya.
     *
     * Pembagiannya disengaja: penataan ulang baris murni dan dapat diuji tanpa
     * database (PurchaseRequestSummaryService::normaliseItems), sementara
     * pembekuan label menuntut pembacaan `branches` dan `delivery_projects` —
     * itu pekerjaan di sini.
     *
     * `withTrashed()` pada cabang: baris lama dapat menunjuk cabang yang sudah
     * dihapus, dan Edit tidak boleh kehilangan labelnya karena itu.
     */
    private function prepareItems(array $rawItems, PurchaseRequestSetting $settings): array
    {
        $items = $this->summary->normaliseItems($rawItems, $settings->defaultUnit());

        if ($items === []) {
            return [];
        }

        $branches = Branch::withTrashed()->get(['id', 'code', 'name'])->keyBy('id');
        $projects = DeliveryProject::get(['id', 'io_number', 'name'])->keyBy('id');

        foreach ($items as &$item) {
            if ($item['cost_center_type'] === PurchaseRequestItem::COST_CENTER_PROJECT) {
                $project = $item['delivery_project_id'] !== null
                    ? $projects->get($item['delivery_project_id'])
                    : null;

                $item['cost_center_label'] = $project ? $this->projectLabel($project) : null;

                continue;
            }

            $branch = $item['branch_id'] !== null ? $branches->get($item['branch_id']) : null;

            $item['cost_center_label'] = $branch ? $this->branchLabel($branch) : null;
        }

        return $items;
    }

    /** "EC-JOGJA – Eclectic Yogyakarta" (Keputusan D127). */
    private function branchLabel(Branch $branch): string
    {
        return trim($branch->code . ' – ' . $branch->name);
    }

    /**
     * "7600000084 – Implementasi SAP PM" (Keputusan D127).
     *
     * `io_number` dipakai karena itulah padanan terdekat kode cost center pada
     * aplikasi acuan, dan karena nama proyek terlalu panjang untuk berdiri
     * sendiri di kolom cetakan. Proyek tanpa io_number jatuh ke namanya saja.
     */
    private function projectLabel(DeliveryProject $project): string
    {
        $io = trim((string) $project->io_number);

        return $io !== '' ? $io . ' – ' . $project->name : (string) $project->name;
    }

    private function writeItems(PurchaseRequest $request, array $items): void
    {
        foreach ($items as $item) {
            $request->items()->create($item);
        }
    }

    /**
     * Validasi penyetuju yang dipilih pemohon (Keputusan D126).
     *
     * @return array{allowed: bool, reason: string, chosen: array<int, int>}  [order_seq => employee_id]
     */
    private function resolveChosenApprovers(Collection $steps, array $payload): array
    {
        $chosen = [];

        foreach ($steps as $step) {
            if (!$step->requester_selectable) {
                continue;
            }

            $candidates = $step->candidateEmployeeIds();

            // Langkah selectable tanpa kandidat seharusnya sudah ditolak di
            // halaman Settings. Diperiksa lagi di sini karena data dapat berubah
            // di antara keduanya — role yang kandidatnya habis, misalnya.
            if ($candidates === []) {
                return [
                    'allowed' => false,
                    'reason'  => 'Step "' . $step->name . '" has no approver to choose from. Ask HR to fix it in Purchase Request Settings.',
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

            if (!in_array($picked, $candidates, true)) {
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
     * Salin definisi langkah ke dokumen — lihat docblock migrasinya.
     *
     * 🔴 Untuk langkah yang penyetujunya dipilih pemohon, `approver_employee_ids`
     * diisi TEPAT SATU id, bukan seluruh daftar kandidat. Itulah mekanisme
     * pembekuan D126: mengganti kandidat di Settings kelak tidak mengubah
     * penyetuju dokumen yang sedang menunggu.
     *
     * @param  array<int, int>  $chosen  [order_seq => employee_id]
     */
    private function snapshotSteps(PurchaseRequest $request, Collection $steps, array $chosen = []): void
    {
        foreach ($steps as $step) {
            $pick = $chosen[$step->order_seq] ?? null;

            PurchaseRequestApproval::create([
                'purchase_request_id'   => $request->id,
                'order_seq'             => $step->order_seq,
                'step_name'             => $step->name,
                'approver_type'         => $step->approver_type,
                'approver_role_id'      => $step->approver_role_id,
                'approver_employee_ids' => $pick !== null ? [$pick] : $step->approver_employee_ids,
                'chosen_by_requester'   => $pick !== null,
                'status'                => PurchaseRequestApproval::STATUS_WAITING,
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
        return PurchaseRequest::query()
            ->whereIn('status', PurchaseRequest::OPEN_STATUSES)
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
     * Sebaliknya MENGHAPUS atau MELONGGARKAN langkah TIDAK PERNAH berlaku
     * surut. Di sanalah bahayanya: dokumen yang menunggu di langkah yang dihapus
     * bisa melompat jadi disetujui tanpa ditinjau siapa pun.
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
    public function applyStepToOpenRequests(PurchaseRequestApprovalStep $step, ?int $actorId = null): array
    {
        $targets = PurchaseRequest::query()
            ->whereIn('status', PurchaseRequest::OPEN_STATUSES)
            ->where('current_step_order', '<', $step->order_seq)
            ->get();

        if ($targets->isEmpty()) {
            return ['applied' => 0, 'request_nos' => []];
        }

        $applied = [];

        DB::transaction(function () use ($targets, $step, &$applied) {
            foreach ($targets as $request) {
                $exists = $request->approvals()
                    ->where('order_seq', $step->order_seq)
                    ->exists();

                if ($exists) {
                    continue;
                }

                PurchaseRequestApproval::create([
                    'purchase_request_id'   => $request->id,
                    'order_seq'             => $step->order_seq,
                    'step_name'             => $step->name,
                    'approver_type'         => $step->approver_type,
                    'approver_role_id'      => $step->approver_role_id,
                    'approver_employee_ids' => $step->approver_employee_ids,
                    'chosen_by_requester'   => false,
                    'status'                => PurchaseRequestApproval::STATUS_WAITING,
                ]);

                $this->addFlag($request, PurchaseRequest::FLAG_WORKFLOW_EXTENDED);

                $applied[] = $request->request_no;
            }
        });

        if ($applied !== []) {
            Log::info('Purchase request approval step applied to in-progress documents.', [
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
     * Nomor dokumen berikutnya: PR/2026/08/00001 (Keputusan D124).
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
        $prefix = sprintf('PR/%s/%s/', $date->format('Y'), $date->format('m'));

        $last = PurchaseRequest::withTrashed()
            ->where('request_no', 'like', $prefix . '%')
            ->lockForUpdate()
            ->orderByDesc('request_no')
            ->value('request_no');

        $next = $last ? ((int) substr($last, strlen($prefix))) + 1 : 1;

        return $prefix . str_pad((string) $next, 5, '0', STR_PAD_LEFT);
    }

    private function addFlag(PurchaseRequest $request, string $flag): void
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

    /** Nama yang ditampilkan; pola yang sama dengan ReimbursementService. */
    private function nameOf(?Employee $employee): string
    {
        return $employee?->basicData?->nick_name ?? $employee?->eci ?? '—';
    }

    /**
     * Beri tahu karyawan perkembangan dokumennya.
     *
     * Dibungkus try/catch karena kegagalan mengirim notifikasi tidak boleh
     * membatalkan persetujuan yang sudah tersimpan — tetapi tetap dicatat ke log
     * supaya kegagalannya tidak hilang diam-diam (Keputusan D44).
     */
    private function notify(PurchaseRequest $request, string $outcome, ?string $notes = null): void
    {
        try {
            $items = $request->itemCountLabel();

            $message = match ($outcome) {
                'approved'   => "Your purchase request {$request->request_no} ({$items}) was approved.",
                'rejected'   => "Your purchase request {$request->request_no} ({$items}) was rejected.",
                'progressed' => "Your purchase request {$request->request_no} passed a review step.",
                default      => "Your purchase request {$request->request_no} was updated.",
            };

            Notification::create([
                'employee_id'      => $request->employee_id,
                'type'             => 'purchase_request_' . $outcome,
                'from_employee_id' => session('user.id'),
                'preview'          => $message . ($notes ? ' Note: ' . $notes : ''),
                'link'             => '/general/my-purchase-request',
            ]);
        } catch (\Throwable $e) {
            Log::error('Failed to send purchase request notification.', [
                'request_id' => $request->id,
                'outcome'    => $outcome,
                'message'    => $e->getMessage(),
            ]);
        }
    }
}
