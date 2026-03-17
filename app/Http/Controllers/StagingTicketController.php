<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\StagingTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * StagingTicketController
 *
 * Endpoint yang dipakai:
 *   - Customer Project (Jarvies) → POST /api/staging-tickets    (submit tiket baru)
 *   - Employee/Admin (EcoSystem)  → GET  /api/staging-tickets    (list untuk validasi)
 *                                 → GET  /api/staging-tickets/{id}
 *                                 → POST /api/staging-tickets/{id}/approve
 *                                 → POST /api/staging-tickets/{id}/reject
 *   - Admin web view              → GET  /staging-tickets        (halaman validasi)
 */
class StagingTicketController extends Controller
{
    public function __construct(private StagingTicketService $service) {}

    // ─── Web view (admin) ─────────────────────────────────────────────────────

    /**
     * Halaman validasi staging ticket untuk admin/helpdesk.
     */
    public function view(Request $request)
    {
        $sessionUser = session('user');
        $user = (object) [
            'role' => (object) ['role_id' => $sessionUser['role']['id'] ?? 0],
        ];

        // Hanya admin (1) dan helpdesk (6,7) yang boleh akses
        if (!in_array($user->role->role_id, [1, 2, 6, 7])) {
            abort(403, 'Unauthorized');
        }

        return view('staging.index', compact('user'));
    }

    /**
     * Rejected staging tickets page for admin/helpdesk.
     */
    public function viewRejected(Request $request)
    {
        $sessionUser = session('user');
        $user = (object) [
            'role' => (object) ['role_id' => $sessionUser['role']['id'] ?? 0],
        ];

        if (!in_array($user->role->role_id, [1, 2, 6, 7])) {
            abort(403, 'Unauthorized');
        }

        return view('staging.rejected', compact('user'));
    }

    // ─── API: List staging tickets ────────────────────────────────────────────

    /**
     * GET /api/staging-tickets
     * Query params: status (unvalidated|approved|rejected), customer_id, per_page
     */
    public function index(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'];

        if (in_array($roleId, [1, 2, 3, 6, 7])) {
            $query = StagingTicket::query();
        } else {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $query->with(['customer.basicData', 'validator.basicData'])
              ->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $data    = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => $data->map(fn ($s) => $this->formatStaging($s)),
            'meta'    => [
                'total'        => $data->total(),
                'per_page'     => $data->perPage(),
                'current_page' => $data->currentPage(),
                'last_page'    => $data->lastPage(),
            ],
        ]);
    }

    // ─── API: Show single ─────────────────────────────────────────────────────

    public function show($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $staging = StagingTicket::with(['customer.basicData', 'validator.basicData', 'ticket'])
            ->findOrFail($id);

        return response()->json(['success' => true, 'data' => $this->formatStaging($staging)]);
    }

    // ─── API: Customer submit (dari Jarvies) ──────────────────────────────────

    /**
     * POST /api/staging-tickets
     * Dipanggil oleh Customer Project (Jarvies) saat customer submit tiket.
     */
    public function store(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'description'        => 'required|string|max:5000',
            'body'               => 'nullable|string',               // Isi tiket dari Jarvies (form body)
            'ticket_priority'    => 'required|in:Very High,High,Medium,Low',
            'sender_name'        => 'nullable|string|max:255',
            'submitted_by_email' => 'nullable|email|max:255',        // Email login customer
            'cc_emails'          => 'nullable|string',               // JSON string: ["a@x.com","b@y.com"]
            // Field tambahan (opsional)
            'name'               => 'nullable|string|max:255',       // Nama contact person
            'no_hp'              => 'nullable|string|max:255',       // Nomor HP
            'module'             => 'nullable|string|max:255',       // Modul terkait
            'client'             => 'nullable|string|max:255',       // Nama client
        ]);

        try {
            $staging = $this->service->createFromWeb($validated, $sessionUser['id']);

            return response()->json([
                'success' => true,
                'message' => 'Your ticket has been submitted and is awaiting admin validation.',
                'data'    => $this->formatStaging($staging),
            ], 201);
        } catch (\Exception $e) {
            Log::error('StagingTicketController@store: gagal simpan staging', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── API: Admin approve ───────────────────────────────────────────────────

    /**
     * POST /api/staging-tickets/{id}/approve
     * Admin menyetujui staging → dibuat ticket resmi.
     */
    public function approve(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'];
        if (!in_array($roleId, [1, 2, 6, 7])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ticket_type'     => 'required|string|in:Incident,Service Request,Change Request,Consult',
            'ticket_priority' => 'required|string|in:Very High,High,Medium,Low',
        ]);

        $staging = StagingTicket::findOrFail($id);

        try {
            $ticketType     = $request->input('ticket_type');
            $ticketPriority = $request->input('ticket_priority');
            $result         = $this->service->approve($staging, $sessionUser['id'], $ticketType, $ticketPriority);
            $ticket       = $result['ticket'];
            $firstMessage = $result['first_message'];

            // Jika staging dari email dan punya attachment → proses via Graph sekarang.
            // Cek has_attachments flag ATAU ada cid: di body (beberapa email client salah lapor).
            $hasCidInBody = str_contains($staging->email_body_html ?? '', 'cid:');
            if ($firstMessage && ($staging->has_attachments || $hasCidInBody) && $staging->graph_message_id) {
                try {
                    app(\App\Http\Controllers\EmailController::class)
                        ->processAttachmentsForMessage(
                            $staging->graph_message_id,
                            $firstMessage,
                            $ticket->ticket_id
                        );
                } catch (\Exception $e) {
                    // Attachment gagal tidak membatalkan approve — ticket sudah dibuat
                    Log::warning('StagingTicketController@approve: gagal proses attachment email', [
                        'staging_id' => $staging->id,
                        'error'      => $e->getMessage(),
                    ]);
                }
            }

            // Kirim notifikasi balasan otomatis ke customer
            $this->sendApprovalNotification($staging, $ticket, $sessionUser);

            return response()->json([
                'success' => true,
                'message' => 'Ticket validated and created successfully.',
                'data'    => [
                    'staging_id'       => $staging->id,
                    'ticket_id'        => $ticket->ticket_id,
                    'ticket_number'    => $ticket->ticket_number,
                    'first_message_id' => $firstMessage?->id,
                ],
            ]);
        } catch (\LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('StagingTicketController@approve: gagal approve', [
                'staging_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── API: Admin reject ────────────────────────────────────────────────────

    /**
     * POST /api/staging-tickets/{id}/reject
     * Admin menolak staging ticket.
     */
    public function reject(Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'];
        if (!in_array($roleId, [1, 2, 6, 7])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'reason' => 'required|string|max:1000',
        ]);

        $staging = StagingTicket::findOrFail($id);

        try {
            $this->service->reject($staging, $sessionUser['id'], $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Ticket has been rejected.',
                'data'    => ['staging_id' => $staging->id, 'status' => 'rejected'],
            ]);
        } catch (\LogicException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 422);
        } catch (\Exception $e) {
            Log::error('StagingTicketController@reject: gagal reject', [
                'staging_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject ticket: ' . $e->getMessage(),
            ], 500);
        }
    }

    // ─── API: Statistics (untuk badge/notif admin) ────────────────────────────

    /**
     * GET /api/staging-tickets/statistics
     */
    public function statistics()
    {
        $sessionUser = session('user');
        if (!$sessionUser || !in_array($sessionUser['role']['id'], [1, 2, 6, 7])) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'unvalidated' => StagingTicket::unvalidated()->count(),
                'approved'    => StagingTicket::approved()->count(),
                'rejected'    => StagingTicket::rejected()->count(),
                'total'       => StagingTicket::count(),
            ],
        ]);
    }

    // ─── Private: Notifikasi balasan otomatis setelah approval ───────────────

    /**
     * Kirim balasan otomatis ke customer saat staging ticket disetujui.
     *
     * - Untuk semua channel: simpan TicketMessage ke DB (tampil di Jarvies)
     * - Untuk email channel: juga kirim email via Graph API
     *
     * Template:
     *   "Baik akan disampaikan dengan Nomor Ticket XXXX
     *   Best Regards,
     *   {nama helpdesk}"
     *
     * Subject email: "{ticket_number}, {original_subject}"
     * Contoh: "2603-CUST-000001, issue maret"
     */
    private function sendApprovalNotification(StagingTicket $staging, Ticket $ticket, array $sessionUser): void
    {
        try {
            // ── Ambil nama pengirim (helpdesk yang approve) ──
            $nickName = $sessionUser['nick_name'] ?? null;
            if (!$nickName) {
                $nickName = DB::table('employee_basic_data')
                    ->where('employee_id', $sessionUser['id'])
                    ->value('nick_name');
            }
            $signatureName = $nickName ?? explode(' ', $sessionUser['name'] ?? 'Helpdesk')[0];

            $ticketNumber = $ticket->ticket_number ?? '—';

            $bodyPlain = "Baik akan disampaikan dengan Nomor Ticket {$ticketNumber}\n\nBest Regards,\n{$signatureName}";
            $bodyHtml  = "<p>Baik akan disampaikan dengan Nomor Ticket <strong>{$ticketNumber}</strong></p>"
                       . "<br><p>Best Regards,<br><strong>{$signatureName}</strong></p>";

            // ── Simpan TicketMessage (tampil di semua channel — termasuk Jarvies web) ──
            $message = TicketMessage::create([
                'ticket_id'           => $ticket->ticket_id,
                'sender_type'         => 'employee',
                'sender_id'           => $sessionUser['id'],
                'sender_name'         => 'Helpdesk Support',
                'message'             => $bodyPlain,
                'message_html'        => $bodyHtml,
                'is_internal_note'    => false,
                'channel'             => $ticket->channel,
                'is_read_by_customer' => false,
                'is_read_by_agent'    => true,
            ]);

            $ticket->update([
                'last_agent_reply_at' => now(),
                'last_message_at'     => now(),
            ]);

            // ── Selalu kirim email ke customer jika ada alamat email ──────────────
            $customerEmail = $staging->submitted_by_email;
            if (!$customerEmail && $ticket->customer_id) {
                $customerEmail = Customer::find($ticket->customer_id)?->email;
            }

            if ($customerEmail) {
                // Subject sama dengan format reply: "Ticket #XXXX: description"
                $subject   = 'Ticket #' . $ticketNumber . ': ' . ($staging->description ?? 'Ticket Update');
                $inReplyTo = $staging->email_message_id; // null untuk web-only → buat thread baru

                // Ambil CC dari staging (bisa format string array atau object array)
                $ccList = [];
                if (!empty($staging->cc_emails)) {
                    $decoded = json_decode($staging->cc_emails, true);
                    if (is_array($decoded)) {
                        $ccList = $decoded; // sendTicketReply handles both string[] and object[]
                    }
                }

                $emailResult = app(EmailController::class)->sendTicketReply(
                    $customerEmail,
                    $subject,
                    $bodyHtml,
                    $inReplyTo,
                    [],      // files
                    $ccList, // ccList dari staging
                    true     // noRePrefix — subject langsung tanpa "Re: "
                );

                // Simpan conversationId ke ticket agar reply berikutnya bisa threaded
                $newConvId = $emailResult['conversation_id'] ?? null;
                if ($newConvId && empty($ticket->email_thread_id)) {
                    $ticket->update(['email_thread_id' => $newConvId]);
                }

                // Simpan internetMessageId + update channel ke 'email'
                // agar Jarvies bisa pakai sebagai In-Reply-To dan indicator thread aktif tampil
                $internetMsgId = $emailResult['internet_message_id'] ?? null;
                if ($internetMsgId) {
                    $message->update([
                        'email_message_id' => $internetMsgId,
                        'channel'          => 'email',
                    ]);
                }
            }

            Log::info('StagingTicketController@sendApprovalNotification: notifikasi approval terkirim', [
                'ticket_id'     => $ticket->ticket_id,
                'ticket_number' => $ticketNumber,
                'channel'       => $ticket->channel,
            ]);

        } catch (\Exception $e) {
            Log::warning('StagingTicketController@sendApprovalNotification: gagal', [
                'staging_id' => $staging->id,
                'ticket_id'  => $ticket->ticket_id,
                'error'      => $e->getMessage(),
            ]);
            // Tidak throw — gagal notifikasi tidak membatalkan approval yang sudah berhasil
        }
    }

    // ─── Private formatter ────────────────────────────────────────────────────

    private function formatStaging(StagingTicket $s): array
    {
        $customerName = null;
        if ($s->customer) {
            $bd = $s->customer->basicData;
            $customerName = $bd ? trim(($bd->title ?? '') . ' ' . ($bd->name_1 ?? '')) : null;
        }

        $validatorName = null;
        if ($s->validator) {
            $bd = $s->validator->basicData;
            $validatorName = $bd ? trim(($bd->first_name ?? '') . ' ' . ($bd->last_name ?? '')) : null;
        }

        return [
            'id'                  => $s->id,
            'customer_id'         => $s->customer_id,
            'customer_name'       => $customerName,
            'submitted_by_email'  => $s->submitted_by_email,
            'sender_name'         => $s->sender_name,
            'cc_emails'           => $s->cc_emails,
            'description'         => $s->description,
            'ticket_priority'     => $s->ticket?->ticket_priority ?? $s->ticket_priority,
            'ticket_type'         => $s->ticket?->ticket_type,
            'status'              => $s->status,
            'rejection_reason'    => $s->rejection_reason,
            'channel'             => $s->channel,
            'email_thread_id'     => $s->email_thread_id,
            'email_body_html'     => $s->email_body_html,
            'has_attachments'     => $s->has_attachments,
            'validated_by'        => $s->validated_by,
            'validator_name'      => $validatorName,
            'validated_at'        => $s->validated_at?->toDateTimeString(),
            'ticket_id'           => $s->ticket_id,
            'ticket_number'       => $s->ticket?->ticket_number,
            'created_at'          => $s->created_at?->toDateTimeString(),
            // Field tambahan
            'name'                => $s->name,
            'no_hp'               => $s->no_hp,
            'module'              => $s->module,
            'client'              => $s->client,
        ];
    }
}
