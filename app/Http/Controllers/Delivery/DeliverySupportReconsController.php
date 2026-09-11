<?php

namespace App\Http\Controllers\Delivery;

use App\Exceptions\ReconsValidationException;
use App\Exports\DeliverySupportReconsExport;
use App\Http\Controllers\Controller;
use App\Models\DeliverySupport;
use App\Models\DeliverySupportRecons;
use App\Models\DeliverySupportReconsTicket;
use App\Models\Employee;
use App\Models\Ticket;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * ============================================================================
 * DELIVERY SUPPORT — RECONS
 * ============================================================================
 *
 * Rekonsiliasi tiket: mengumpulkan tiket yang sudah closed dan punya Customer
 * MD ke dalam batch "Recons" untuk keperluan penagihan/berita acara.
 *
 * Aturan penting:
 * - Tiket yang boleh masuk Recons baru = tiket milik support ini (ter-link ke
 *   Activity), status `closed`, `man_days` > 0, dan BELUM pernah masuk Recons
 *   manapun milik support ini (termasuk yang masih draft).
 * - Recons berstatus `submitted` terkunci: header maupun daftar tiketnya tidak
 *   bisa diubah dan tidak bisa dihapus.
 * - Seluruh route memakai GET/POST saja (tanpa PUT/PATCH/DELETE) karena
 *   sebagian environment produksi memblokir verb tersebut.
 *
 * @date 2026-09-03
 */
class DeliverySupportReconsController extends Controller
{
    // =========================================================================
    // HALAMAN
    // =========================================================================

    /** GET /delivery/support/{support}/recons/create — screen New Recons. */
    public function create(DeliverySupport $support)
    {
        $support->load('client.basicData');

        return view('delivery.support.recons.form', [
            'support'           => $support,
            'recons'            => null,
            // Sekadar pratinjau — nomor final dibuat saat disimpan supaya
            // counter tidak "bolong" kalau halaman dibuka lalu ditinggalkan.
            'previewNumber'     => DeliverySupportRecons::nextNumberFor($support),
            'selectedTicketIds' => [],
        ]);
    }

    /** GET /delivery/support/{support}/recons/{recons}/edit — edit draft. */
    public function edit(DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($redirect = $this->guardBelongsTo($support, $recons)) {
            return $redirect;
        }

        // Recons yang sudah disubmit tidak boleh diedit — arahkan ke tampilan detail.
        if ($recons->isSubmitted()) {
            return redirect()
                ->route('delivery.support.recons.show', [$support->id, $recons->id])
                ->with('warning', 'This recons has been submitted and can no longer be edited.');
        }

        $support->load('client.basicData');
        $recons->load('lines');

        return view('delivery.support.recons.form', [
            'support'           => $support,
            'recons'            => $recons,
            // Draft yang sudah ada tetap memakai nomor yang sudah terbit.
            'previewNumber'     => $recons->recons_number,
            'selectedTicketIds' => $recons->lines->pluck('ticket_id')->all(),
        ]);
    }

    /** GET /delivery/support/{support}/recons/{recons} — detail batch (read-only). */
    public function show(DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($redirect = $this->guardBelongsTo($support, $recons)) {
            return $redirect;
        }

        $support->load('client.basicData');
        $recons->load(['createdBy.basicData', 'submittedBy.basicData']);

        return view('delivery.support.recons.show', [
            'support' => $support,
            'recons'  => $recons,
            'rows'    => $this->reconsTicketRows($recons),
            'summary' => $this->reconsSummary($recons),
        ]);
    }

    // =========================================================================
    // DATA (JSON) — dipakai tab Recons di halaman Support Details
    // =========================================================================

    /**
     * GET /delivery/support/{support}/recons/tickets
     *
     * SELURUH tiket milik support ini (apa pun statusnya) + kolom info Recons
     * bila tiket tersebut sudah masuk ke sebuah batch.
     */
    public function tickets(DeliverySupport $support)
    {
        // Sub-query info Recons per tiket. Dibuat sebagai satu join tunggal
        // (bukan dua leftJoin berantai) supaya satu tiket TIDAK PERNAH
        // menghasilkan lebih dari satu baris. Sebuah tiket hanya boleh berada
        // di satu Recons di seluruh sistem — lihat eligibleTicketQuery().
        $reconsInfo = DB::table('delivery_support_recons_tickets as rt')
            ->join('delivery_support_recons as r', 'r.id', '=', 'rt.delivery_support_recons_id')
            ->select([
                'rt.ticket_id',
                'r.id as recons_id',
                'r.recons_number',
                'r.description as recons_description',
                'r.status as recons_status',
            ]);

        $rows = DB::table('delivery_support_activities as act')
            ->join('ticket as t', 'act.ticket_id', '=', 't.ticket_id')
            // ticket_sla.ticket_id UNIQUE → join 1:1, tidak menggandakan baris.
            ->leftJoin('ticket_sla as sla', 'sla.ticket_id', '=', 't.ticket_id')
            // Customer MD = total proposal mandays yang approved (bukan ticket.man_days).
            ->leftJoinSub($this->customerMdSubquery(), 'cmd', 'cmd.ticket_id', '=', 't.ticket_id')
            ->leftJoinSub($reconsInfo, 'r', 'r.ticket_id', '=', 't.ticket_id')
            ->where('act.delivery_support_id', $support->id)
            ->whereNotNull('act.ticket_id')
            // Satu tiket bisa ter-link ke lebih dari satu activity; ambil sekali saja.
            ->distinct()
            ->orderByDesc('t.ticket_id')
            ->get([
                't.ticket_id',
                't.ticket_number',
                't.description',
                't.start_date',
                // Tiga kolom di bawah dipakai bersama oleh closeDateOf().
                't.end_date',
                'sla.resolved_at as sla_resolved_at',
                't.updated_at',
                't.status',
                't.ticket_type',
                'cmd.customer_md',
                // Nama kolom sudah di-alias di dalam $reconsInfo.
                'r.recons_id',
                'r.recons_number',
                'r.recons_description',
                'r.recons_status',
            ]);

        return response()->json([
            'success' => true,
            'tickets' => $rows->map(fn ($row) => $this->formatTicketRow($row))->values(),
        ]);
    }

    /**
     * GET /delivery/support/{support}/recons/eligible-tickets
     *
     * Tiket yang boleh dimasukkan ke Recons: closed + ada Customer MD + belum
     * pernah masuk Recons manapun milik support ini. Saat mengedit draft,
     * tiket yang sudah ada di draft itu tetap disertakan supaya bisa tampil
     * tercentang (dan bisa dilepas).
     */
    public function eligibleTickets(Request $request, DeliverySupport $support)
    {
        $currentReconsId = $request->query('recons_id') ? (int) $request->query('recons_id') : null;

        $rows = $this->eligibleTicketQuery($support, $currentReconsId)
            ->get([
                't.ticket_id',
                't.ticket_number',
                't.description',
                't.start_date',
                't.end_date',
                'sla.resolved_at as sla_resolved_at',
                't.updated_at',
                't.status',
                't.ticket_type',
                'cmd.customer_md',
            ])
            ->map(fn ($row) => $this->formatTicketRow($row) + ['in_recons' => false, 'eligible_now' => true])
            ->keyBy('ticket_id');

        // Baris yang SUDAH ada di batch ini WAJIB ikut ditampilkan, walau kini
        // tidak lagi memenuhi syarat (tiket di-reopen, Customer MD dicabut,
        // aturan berubah, dst). Tanpa ini tiket tersebut tetap ikut terpilih di
        // form tapi tidak pernah dirender — user tak bisa melihat apalagi
        // melepasnya, sehingga draft menjadi mustahil disimpan.
        if ($currentReconsId) {
            $lines = DB::table('delivery_support_recons_tickets as rt')
                ->join('ticket as t', 't.ticket_id', '=', 'rt.ticket_id')
                ->leftJoin('ticket_sla as sla', 'sla.ticket_id', '=', 't.ticket_id')
                ->where('rt.delivery_support_recons_id', $currentReconsId)
                ->get([
                    't.ticket_id',
                    't.ticket_number',
                    't.description',
                    't.start_date',
                    't.end_date',
                    'sla.resolved_at as sla_resolved_at',
                    't.updated_at',
                    't.status',
                    't.ticket_type',
                    // MD yang ditampilkan = nilai yang benar-benar tercatat di
                    // batch (snapshot), supaya Total MD di layar sama dengan
                    // yang tersimpan.
                    'rt.man_days_snapshot as customer_md',
                ]);

            foreach ($lines as $line) {
                $existing = $rows->get($line->ticket_id);

                $rows->put($line->ticket_id, $this->formatTicketRow($line) + [
                    'in_recons'    => true,
                    // Masih memenuhi syarat kalau tadi muncul di daftar eligible.
                    'eligible_now' => $existing !== null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'tickets' => $rows->values(),
        ]);
    }

    /**
     * GET /delivery/support/{support}/recons/batches
     *
     * Daftar batch Recons milik support ini + ringkasan jumlah tiket & total MD.
     */
    public function batches(DeliverySupport $support)
    {
        $recons = DeliverySupportRecons::where('delivery_support_id', $support->id)
            ->with(['createdBy.basicData', 'submittedBy.basicData'])
            ->withCount('lines')
            ->withSum('lines', 'man_days_snapshot')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'success' => true,
            'recons'  => $recons->map(fn (DeliverySupportRecons $r) => [
                'id'                 => $r->id,
                'recons_number'      => $r->recons_number,
                'description'        => $r->description,
                'recons_date'        => $r->recons_date?->format('Y-m-d'),
                'recons_date_label'  => $r->recons_date?->format('d M Y'),
                'status'             => $r->status,
                'status_label'       => $r->status_label,
                'ticket_count'       => (int) $r->lines_count,
                'total_md'           => (float) ($r->lines_sum_man_days_snapshot ?? 0),
                'created_by'         => $this->employeeName($r->createdBy),
                'submitted_by'       => $this->employeeName($r->submittedBy),
                'submitted_at_label' => $r->submitted_at?->format('d M Y H:i'),
                'created_at_label'   => $r->created_at?->format('d M Y H:i'),
            ])->values(),
        ]);
    }

    // =========================================================================
    // AKSI TULIS (POST saja)
    // =========================================================================

    /**
     * POST /delivery/support/{support}/recons/save — buat Recons baru.
     * Body: description, recons_date, ticket_ids[], action=draft|submit
     * (nomor TIDAK dikirim client — selalu dibuat sistem).
     */
    public function store(Request $request, DeliverySupport $support)
    {
        $validated = $this->validatePayload($request);

        // Submit dari form baru = create + langsung kunci, jadi butuh izin edit
        // di samping izin manage yang sudah dijaga middleware route.
        if ($validated['action'] === 'submit' && !$this->userCan('delivery-support.recons.edit')) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to submit a recons.',
            ], 403);
        }

        try {
            $recons = $this->createWithGeneratedNumber($support, $validated);
        } catch (ReconsValidationException $e) {
            return $e->toResponse();
        }

        $message = $validated['action'] === 'submit'
            ? 'Recons submitted successfully.'
            : 'Recons draft saved successfully.';

        // Form selalu berpindah ke halaman detail (window.location.href) setelah
        // simpan, jadi toast-nya dititipkan lewat flash session — mekanisme yang
        // sama dipakai DeliverySupportController::store(). Toast dirender di
        // halaman tujuan oleh blok session('success') di dashboard.blade.php.
        session()->flash('success', $message);

        return response()->json([
            'success'     => true,
            'message'     => $message,
            'recons_id'   => $recons->id,
            'redirect_url' => route('delivery.support.recons.show', [$support->id, $recons->id]),
        ], 201);
    }

    /**
     * POST /delivery/support/{support}/recons/{recons}/save — simpan draft yang ada.
     */
    public function update(Request $request, DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($response = $this->guardBelongsToJson($support, $recons)) {
            return $response;
        }

        if ($recons->isSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'This recons has been submitted and can no longer be edited.',
            ], 422);
        }

        $validated = $this->validatePayload($request);

        try {
            DB::transaction(function () use ($validated, $support, $recons) {
                $ticketIds = $this->assertTicketsEligible($support, $validated['ticket_ids'], $recons->id);

                // Nomor TIDAK ikut berubah saat draft disimpan ulang: nomor
                // dokumen terbit sekali saat pembuatan (termasuk bagian yymm-nya),
                // supaya identitas dokumen stabil dan counter tidak terpakai dua kali.
                $recons->update([
                    'description'   => $validated['description'],
                    'recons_date'   => $validated['recons_date'],
                    // Penyimpanan terakhir dianggap "pelaku recons" saat ini.
                    'created_by_id' => $recons->created_by_id ?? session('user.id'),
                ]);

                $this->syncLines($recons, $ticketIds);

                if ($validated['action'] === 'submit') {
                    $recons->update([
                        'status'          => DeliverySupportRecons::STATUS_SUBMITTED,
                        'submitted_by_id' => session('user.id'),
                        'submitted_at'    => now(),
                    ]);
                }
            });
        } catch (ReconsValidationException $e) {
            return $e->toResponse();
        }

        $message = $validated['action'] === 'submit'
            ? 'Recons submitted successfully.'
            : 'Recons draft saved successfully.';

        // Lihat catatan di store(): toast dititipkan lewat flash session karena
        // form berpindah ke halaman detail setelah simpan.
        session()->flash('success', $message);

        return response()->json([
            'success'      => true,
            'message'      => $message,
            'recons_id'    => $recons->id,
            'redirect_url' => route('delivery.support.recons.show', [$support->id, $recons->id]),
        ]);
    }

    /**
     * POST /delivery/support/{support}/recons/{recons}/submit — kunci batch.
     * Dipakai dari halaman detail (tanpa mengubah header/daftar tiket).
     */
    public function submit(Request $request, DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($response = $this->guardBelongsToJson($support, $recons)) {
            return $response;
        }

        if ($recons->isSubmitted()) {
            return response()->json(['success' => false, 'message' => 'This recons has already been submitted.'], 422);
        }

        if ($recons->lines()->count() === 0) {
            return response()->json(['success' => false, 'message' => 'Cannot submit a recons without any ticket.'], 422);
        }

        $recons->update([
            'status'          => DeliverySupportRecons::STATUS_SUBMITTED,
            'submitted_by_id' => session('user.id'),
            'submitted_at'    => now(),
        ]);

        // Halaman detail me-reload dirinya sendiri setelah submit → titipkan toast
        // lewat flash. Pemanggil yang menyegarkan tampilan tanpa reload (mis. tab
        // Recons List) tidak mengirim `redirect` sehingga tetap memakai toast JS.
        $message = 'Recons submitted successfully.';
        if ($request->boolean('redirect')) {
            session()->flash('success', $message);
        }

        return response()->json(['success' => true, 'message' => $message]);
    }

    /**
     * POST /delivery/support/{support}/recons/{recons}/cancel — buka kunci batch.
     *
     * Mengembalikan status `submitted` menjadi `draft` sehingga header dan
     * daftar tiketnya bisa diperbaiki lagi. Tiket di dalamnya TETAP terkunci
     * (tidak bisa dipilih Recons lain) selama masih tercatat di batch ini —
     * baik saat draft maupun submitted.
     */
    public function cancel(Request $request, DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($response = $this->guardBelongsToJson($support, $recons)) {
            return $response;
        }

        if (!$recons->isSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'This recons is already a draft.',
            ], 422);
        }

        $recons->update([
            'status'          => DeliverySupportRecons::STATUS_DRAFT,
            'submitted_by_id' => null,
            'submitted_at'    => null,
        ]);

        // Lihat catatan di submit(): halaman detail me-reload → titipkan toast
        // lewat flash; tab Recons List menyegarkan tanpa reload → tidak dikirim.
        $message = 'Recons has been reverted to draft.';
        if ($request->boolean('redirect')) {
            session()->flash('success', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
        ]);
    }

    /**
     * POST /delivery/support/{support}/recons/{recons}/delete — hapus draft.
     * Batch yang sudah disubmit tidak boleh dihapus.
     */
    public function destroy(DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($response = $this->guardBelongsToJson($support, $recons)) {
            return $response;
        }

        if ($recons->isSubmitted()) {
            return response()->json([
                'success' => false,
                'message' => 'A submitted recons cannot be deleted.',
            ], 422);
        }

        // Baris tiket ikut terhapus (cascade) → tiketnya kembali eligible.
        $recons->delete();

        return response()->json(['success' => true, 'message' => 'Recons draft deleted successfully.']);
    }

    // =========================================================================
    // EXPORT
    // =========================================================================

    /** GET /delivery/support/{support}/recons/{recons}/export — detail tiket ke Excel. */
    public function export(DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($redirect = $this->guardBelongsTo($support, $recons)) {
            return $redirect;
        }

        $support->load('client.basicData');
        $recons->load(['createdBy.basicData', 'submittedBy.basicData']);

        // Nama file = nomor Recons apa adanya (mis. MDRC-SML-2609-0001.xlsx).
        // Hanya karakter yang dilarang sistem berkas Windows/Linux yang diganti
        // '_', supaya kode customer dengan karakter tak lazim tetap aman diunduh.
        $safeNumber = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $recons->recons_number);

        $filename = $safeNumber . '.xlsx';

        return Excel::download(
            new DeliverySupportReconsExport(
                support: $support,
                recons: $recons,
                rows: $this->reconsTicketRows($recons),
                createdByName: $this->employeeName($recons->createdBy),
                submittedByName: $this->employeeName($recons->submittedBy),
            ),
            $filename,
        );
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Query dasar tiket eligible: milik support ini, closed, punya Customer MD,
     * dan belum pernah masuk Recons manapun.
     *
     * Pengecekan "sudah terpakai" sengaja GLOBAL (bukan per Delivery Support):
     * satu ticket_id secara skema bisa ter-link ke activity di lebih dari satu
     * support, dan tiket yang sama tidak boleh direkonsiliasi dua kali di
     * tempat berbeda (dobel hitung MD). Efek sampingnya: satu tiket paling
     * banyak berada di satu Recons di seluruh sistem.
     */
    private function eligibleTicketQuery(DeliverySupport $support, ?int $exceptReconsId)
    {
        // Tiket yang sudah "terpakai" di batch manapun (kecuali batch yang
        // sedang diedit, supaya tiketnya tetap bisa tampil & dilepas).
        $usedTicketIds = DB::table('delivery_support_recons_tickets as rt')
            ->when($exceptReconsId, fn ($q) => $q->where('rt.delivery_support_recons_id', '!=', $exceptReconsId))
            ->pluck('rt.ticket_id');

        return DB::table('delivery_support_activities as act')
            ->join('ticket as t', 'act.ticket_id', '=', 't.ticket_id')
            // Dibutuhkan closeDateOf(); ticket_sla.ticket_id UNIQUE → aman 1:1.
            ->leftJoin('ticket_sla as sla', 'sla.ticket_id', '=', 't.ticket_id')
            // Syarat "ada Customer MD" memakai total proposal mandays yang
            // approved — sama dengan yang ditampilkan Menu Ticket.
            ->joinSub($this->customerMdSubquery(), 'cmd', 'cmd.ticket_id', '=', 't.ticket_id')
            ->where('act.delivery_support_id', $support->id)
            ->whereNotNull('act.ticket_id')
            ->where('t.status', 'closed')
            ->where('cmd.customer_md', '>', 0)
            ->when($usedTicketIds->isNotEmpty(), fn ($q) => $q->whereNotIn('t.ticket_id', $usedTicketIds))
            ->distinct()
            ->orderByDesc('t.ticket_id');
    }

    /**
     * Validasi ulang di server: setiap ticket_id yang dikirim harus benar-benar
     * eligible. Tidak pernah percaya daftar dari browser (bisa basi kalau ada
     * user lain menyimpan Recons untuk tiket yang sama lebih dulu).
     *
     * @return int[] daftar ticket_id yang tervalidasi
     * @throws ReconsValidationException
     */
    private function assertTicketsEligible(DeliverySupport $support, array $ticketIds, ?int $exceptReconsId): array
    {
        $ticketIds = array_values(array_unique(array_map('intval', $ticketIds)));

        if (empty($ticketIds)) {
            throw new ReconsValidationException('Select at least one ticket for this recons.');
        }

        $eligibleIds = $this->eligibleTicketQuery($support, $exceptReconsId)
            ->pluck('t.ticket_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        // Tiket yang SUDAH tercatat di batch ini selalu boleh dipertahankan,
        // sekalipun kini tak lagi memenuhi syarat (mis. Customer MD dicabut
        // atau tiket di-reopen setelah masuk draft). Yang diperiksa ketat hanya
        // tiket yang BARU ditambahkan. Tanpa pengecualian ini, draft yang salah
        // satu tiketnya berubah akan tertolak selamanya dan tidak bisa
        // diperbaiki dari UI.
        if ($exceptReconsId) {
            $existingLineIds = DeliverySupportReconsTicket::where('delivery_support_recons_id', $exceptReconsId)
                ->pluck('ticket_id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $eligibleIds = array_unique(array_merge($eligibleIds, $existingLineIds));
        }

        $invalid = array_diff($ticketIds, $eligibleIds);

        if (!empty($invalid)) {
            $numbers = Ticket::whereIn('ticket_id', $invalid)
                ->pluck('ticket_number', 'ticket_id')
                ->map(fn ($number, $id) => $number ?: ('#' . $id))
                ->values()
                ->all();

            throw new ReconsValidationException(
                'These tickets are no longer eligible (already reconciled, reopened, or their Customer MD was cleared): '
                . implode(', ', $numbers) . '. Please refresh the page and try again.'
            );
        }

        return $ticketIds;
    }

    /**
     * Buat Recons baru dengan nomor yang di-generate otomatis.
     *
     * Nomor memakai counter global (lihat DeliverySupportRecons::nextNumberFor),
     * jadi dua penyimpanan yang nyaris bersamaan bisa menghasilkan counter yang
     * sama. Kebentrokan itu dijaga unique index `uniq_recons_number` di level
     * database dan di sini dicoba ulang beberapa kali — bukan dikunci dengan
     * lock tabel, supaya tidak memperlambat penyimpanan lain.
     *
     * @throws ReconsValidationException
     */
    private function createWithGeneratedNumber(DeliverySupport $support, array $validated): DeliverySupportRecons
    {
        $attempts = 0;

        while (true) {
            $attempts++;

            try {
                return DB::transaction(function () use ($validated, $support) {
                    $ticketIds  = $this->assertTicketsEligible($support, $validated['ticket_ids'], null);
                    $employeeId = session('user.id');

                    $recons = DeliverySupportRecons::create([
                        'delivery_support_id' => $support->id,
                        'recons_number'       => DeliverySupportRecons::nextNumberFor(
                            $support,
                            \Illuminate\Support\Carbon::parse($validated['recons_date']),
                        ),
                        'description'         => $validated['description'],
                        'recons_date'         => $validated['recons_date'],
                        'status'              => DeliverySupportRecons::STATUS_DRAFT,
                        'created_by_id'       => $employeeId,
                    ]);

                    $this->syncLines($recons, $ticketIds);

                    if ($validated['action'] === 'submit') {
                        $recons->update([
                            'status'          => DeliverySupportRecons::STATUS_SUBMITTED,
                            'submitted_by_id' => $employeeId,
                            'submitted_at'    => now(),
                        ]);
                    }

                    return $recons;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Nomor keburu dipakai proses lain — ambil counter berikutnya.
                if ($attempts >= 5) {
                    throw new ReconsValidationException(
                        'Could not generate a unique recons number after several attempts. Please try again.'
                    );
                }
            }
        }
    }

    /** Ganti seluruh baris tiket sebuah batch dengan daftar baru (+ snapshot MD). */
    private function syncLines(DeliverySupportRecons $recons, array $ticketIds): void
    {
        // Snapshot memakai Customer MD (total proposal approved), bukan
        // ticket.man_days — konsisten dengan angka yang dilihat user.
        $manDays = $this->customerMdSubquery()
            ->whereIn('ticket_id', $ticketIds)
            ->pluck('customer_md', 'ticket_id');

        $recons->lines()->whereNotIn('ticket_id', $ticketIds)->delete();

        $existing = $recons->lines()->pluck('ticket_id')->all();

        foreach ($ticketIds as $ticketId) {
            if (in_array($ticketId, $existing, true)) {
                continue;
            }

            DeliverySupportReconsTicket::create([
                'delivery_support_recons_id' => $recons->id,
                'ticket_id'                  => $ticketId,
                'man_days_snapshot'          => $manDays[$ticketId] ?? 0,
            ]);
        }
    }

    /** Baris tiket sebuah batch untuk tampilan detail & export. */
    private function reconsTicketRows(DeliverySupportRecons $recons)
    {
        return DB::table('delivery_support_recons_tickets as rt')
            ->join('ticket as t', 'rt.ticket_id', '=', 't.ticket_id')
            ->leftJoin('ticket_sla as sla', 'sla.ticket_id', '=', 't.ticket_id')
            ->where('rt.delivery_support_recons_id', $recons->id)
            ->orderBy('t.ticket_number')
            ->get([
                't.ticket_id',
                't.ticket_number',
                't.description',
                't.start_date',
                't.end_date',
                'sla.resolved_at as sla_resolved_at',
                't.updated_at',
                't.status',
                't.ticket_type',
                // MD yang ditampilkan adalah snapshot Customer MD saat tiket
                // dimasukkan ke batch — sengaja beku, tidak ikut berubah.
                'rt.man_days_snapshot as customer_md',
            ])
            ->map(fn ($row) => $this->formatTicketRow($row));
    }

    private function reconsSummary(DeliverySupportRecons $recons): array
    {
        return [
            'ticket_count' => $recons->lines()->count(),
            'total_md'     => (float) $recons->lines()->sum('man_days_snapshot'),
        ];
    }

    /** Bentuk baris tiket yang seragam untuk semua endpoint & view. */
    private function formatTicketRow($row): array
    {
        $start = $row->start_date ? \Illuminate\Support\Carbon::parse($row->start_date) : null;
        $close = ($raw = $this->closeDateOf($row)) ? \Illuminate\Support\Carbon::parse($raw) : null;

        return [
            'ticket_id'          => (int) $row->ticket_id,
            'ticket_number'      => $row->ticket_number,
            'description'        => $row->description,
            'start_date'         => $start?->format('Y-m-d'),
            'start_date_label'   => $start?->format('d M Y') ?? '-',
            'close_date'         => $close?->format('Y-m-d'),
            'close_date_label'   => $close?->format('d M Y') ?? '-',
            'status'             => $row->status,
            'status_label'       => $this->ticketStatusLabel($row->status),
            'type'               => $row->ticket_type,
            // Kunci JSON tetap `man_days` (dipakai JS ketiga halaman), tapi
            // NILAINYA adalah Customer MD sesuai definisi kanonik aplikasi.
            'man_days'           => $row->customer_md !== null ? (float) $row->customer_md : null,
            'recons_id'          => isset($row->recons_id) ? ($row->recons_id ? (int) $row->recons_id : null) : null,
            'recons_number'      => $row->recons_number ?? null,
            'recons_description' => $row->recons_description ?? null,
            'recons_status'      => $row->recons_status ?? null,
        ];
    }

    /**
     * Sub-query "Customer MD" per tiket — definisi kanonik aplikasi ini:
     * TOTAL `customer_mandays.total_mandays` yang berstatus **approved**.
     *
     * Sumbernya `TicketController` (baris ~559 & ~790) yang membangun
     * `$customerMandaysMap` dengan cara persis sama, lalu dirender Menu Ticket
     * sebagai kolom mandays ([ticket/index.blade.php:1556]).
     *
     * PENTING: ini BUKAN `ticket.man_days`. Kolom itu bisa berisi placeholder
     * headcount (lihat Ticket::refreshPlaceholderManDays) dan pada data nyata
     * sering NULL walau customer sudah menyetujui mandays-nya — sehingga tiket
     * yang seharusnya bisa direkonsiliasi jadi tidak muncul.
     */
    private function customerMdSubquery()
    {
        return DB::table('customer_mandays')
            ->select('ticket_id', DB::raw('SUM(total_mandays) as customer_md'))
            ->where('status', 'approved')
            ->groupBy('ticket_id');
    }

    /**
     * Close Date sebuah tiket — MENIRU PERSIS logika kolom "Close Date" di
     * Menu Ticket, yang memakai rantai fallback bertingkat, bukan satu kolom:
     *
     *   resources/views/ticket/index.blade.php (baris ~1464)
     *   ticket.end_date  ||  (status === 'closed' ? sla.resolved_at || updated_at : null)
     *
     * Alasannya terlihat dari data: dari 1.113 tiket berstatus closed, hanya 1
     * yang punya `ticket.end_date` (pengisian kolom itu baru ditambahkan
     * belakangan di TicketController::updateTicketStatus), sementara 621 punya
     * `ticket_sla.resolved_at`. Kalau Recons hanya membaca `end_date`, kolom
     * Close Date-nya nyaris selalu kosong padahal di Menu Ticket terisi.
     *
     * Logika ini sengaja DISALIN, bukan diubah di Menu Ticket, supaya halaman
     * tiket yang sudah live tidak tersentuh sama sekali.
     */
    private function closeDateOf($row): ?string
    {
        if (!empty($row->end_date)) {
            return (string) $row->end_date;
        }

        if (($row->status ?? null) !== 'closed') {
            return null;
        }

        return !empty($row->sla_resolved_at)
            ? (string) $row->sla_resolved_at
            : (!empty($row->updated_at) ? (string) $row->updated_at : null);
    }

    private function ticketStatusLabel(?string $status): string
    {
        return match ($status) {
            'open'                    => 'Open',
            'inprocess'               => 'Inprocess',
            'waiting_on_customer'     => 'Waiting on Customer',
            'waiting_on_3rd_party'    => 'Waiting on 3rd Party',
            'waiting_to_confirmation' => 'Waiting to Confirmation',
            'hold'                    => 'Hold',
            'cancelled'               => 'Cancelled',
            'closed'                  => 'Closed',
            default                   => ucfirst(str_replace('_', ' ', (string) $status)),
        };
    }

    private function employeeName(?Employee $employee): ?string
    {
        if (!$employee) {
            return null;
        }

        $basic = $employee->basicData;
        $name  = trim(($basic->first_name ?? '') . ' ' . ($basic->last_name ?? ''));

        return $name !== '' ? $name : ('Employee #' . $employee->employee_id);
    }

    private function validatePayload(Request $request): array
    {
        // `recons_number` sengaja TIDAK diterima dari request: nomor selalu
        // dibuat sistem (MDRC-[customer_code]-[yymm]-[xxxx]).
        //
        // `recons_date` & `description` WAJIB diisi (ketentuan pemilik sistem).
        // Form sudah memvalidasinya lebih dulu demi umpan balik seketika; aturan
        // di sini adalah penjaga terakhir, karena request bisa saja datang dari
        // luar form tersebut.
        $validated = $request->validate([
            'description'   => 'required|string|max:2000',
            'recons_date'   => 'required|date',
            'ticket_ids'    => 'required|array|min:1',
            'ticket_ids.*'  => 'integer',
            'action'        => 'required|string|in:draft,submit',
        ], [
            'description.required' => 'Description is required.',
            'recons_date.required' => 'Recons date is required.',
            'recons_date.date'     => 'Recons date is not a valid date.',
            'ticket_ids.required'  => 'Select at least one ticket for this recons.',
            'ticket_ids.min'       => 'Select at least one ticket for this recons.',
        ]);

        return [
            'description'   => trim($validated['description']),
            'recons_date'   => $validated['recons_date'],
            'ticket_ids'    => $validated['ticket_ids'],
            'action'        => $validated['action'],
        ];
    }

    private function userCan(string $slug): bool
    {
        return (bool) Employee::find(session('user.id'))?->hasPermission($slug);
    }

    /** Pastikan batch memang milik support di URL (halaman). */
    private function guardBelongsTo(DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($recons->delivery_support_id !== $support->id) {
            return redirect()
                ->route('delivery.support.show', $support->id)
                ->with('error', 'Recons not found for this delivery support.');
        }

        return null;
    }

    /** Versi JSON dari guardBelongsTo() untuk endpoint AJAX. */
    private function guardBelongsToJson(DeliverySupport $support, DeliverySupportRecons $recons)
    {
        if ($recons->delivery_support_id !== $support->id) {
            return response()->json(['success' => false, 'message' => 'Recons not found for this delivery support.'], 404);
        }

        return null;
    }
}
