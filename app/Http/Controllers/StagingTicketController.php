<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Models\Customer;
use App\Models\StagingAttachment;
use App\Models\StagingTicket;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Services\SlaService;
use App\Services\StagingTicketService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

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
        if (!in_array($user->role->role_id, array_merge([RoleId::ADMIN->value, RoleId::EMPLOYEE->value], RoleId::HELPDESK_GROUP), true)) {
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

        if (!in_array($user->role->role_id, array_merge([RoleId::ADMIN->value, RoleId::EMPLOYEE->value], RoleId::HELPDESK_GROUP), true)) {
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
            Log::warning('StagingTicketController@index: unauthorized — no session user', [
                'session_id' => session()->getId(),
                'ip'         => $request->ip(),
            ]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $roleId = $sessionUser['role']['id'] ?? null;

        Log::info('StagingTicketController@index: request received', [
            'user_id'     => $sessionUser['id'] ?? null,
            'role_id'     => $roleId,
            'filters'     => $request->only(['status', 'customer_id', 'per_page']),
            'session_id'  => session()->getId(),
            'ip'          => $request->ip(),
        ]);

        if (in_array($roleId, array_merge([RoleId::ADMIN->value, RoleId::EMPLOYEE->value, RoleId::INTERNSHIP->value], RoleId::HELPDESK_GROUP), true)) {
            $query = StagingTicket::query();
        } else {
            Log::warning('StagingTicketController@index: forbidden — role not allowed', [
                'user_id' => $sessionUser['id'] ?? null,
                'role_id' => $roleId,
            ]);
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        // Filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        $query->with(['customer.basicData', 'endCustomer.basicData', 'validator.basicData'])
              ->orderBy('created_at', 'desc');

        $perPage = max(1, min((int) $request->get('per_page', 20), 100));

        try {
            $data = $query->paginate($perPage);
        } catch (\Exception $e) {
            Log::error('StagingTicketController@index: query failed', [
                'error'   => $e->getMessage(),
                'user_id' => $sessionUser['id'] ?? null,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve staging tickets. A database error occurred.'], 500);
        }

        Log::info('StagingTicketController@index: success', [
            'total'   => $data->total(),
            'per_page' => $data->perPage(),
            'user_id' => $sessionUser['id'] ?? null,
        ]);

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

        $staging = StagingTicket::with(['customer.basicData', 'endCustomer.basicData', 'validator.basicData', 'ticket', 'attachments'])
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
            'description'          => 'required|string|max:5000',
            'body'                 => 'nullable|string',               // Isi tiket dari Jarvies (form body)
            'ticket_priority'      => 'required|in:Very High,High,Medium,Low',
            'sender_name'          => 'nullable|string|max:255',
            'submitted_by_email'   => 'nullable|email|max:255',        // Email login customer
            'cc_emails'            => 'nullable|string',               // JSON string: ["a@x.com","b@y.com"]
            'internet_message_id'  => 'nullable|string|max:1000',     // internetMessageId email [Menunggu Validasi] dari Jarvies
            // Field tambahan (opsional)
            'name'                 => 'nullable|string|max:255',       // Nama contact person
            'no_hp'                => 'nullable|string|max:255',       // Nomor HP
            'module'               => 'nullable|string|max:255',       // Modul terkait
            'client'               => 'nullable|string|max:255',       // Nama client
            'contact_id'           => 'nullable|integer',
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
                'message' => 'Failed to submit ticket',
            ], 500);
        }
    }

    // ─── API: JARVIES external submit (via X-Api-Key, tanpa session) ─────────

    /**
     * POST /jarvies/staging-tickets
     * Dipanggil oleh JARVIES saat customer submit tiket dari portal mereka.
     * Autentikasi via X-Api-Key middleware (bukan session).
     * customer_id diambil dari payload, bukan dari session.
     */
    public function jarviesStore(Request $request)
    {
        $validated = $request->validate([
            'description'          => 'required|string|max:5000',
            'body'                 => 'nullable|string',
            'ticket_priority'      => 'nullable|in:Very High,High,Medium,Low',
            'sender_name'          => 'nullable|string|max:255',
            'submitted_by_email'   => 'nullable|email|max:255',
            'cc_emails'            => 'nullable|string',    // JSON string dari JARVIES
            'customer_id'          => 'required|integer',
            'end_customer_id'      => 'nullable|integer',
            'contact_id'           => 'nullable|integer',
            'name'                 => 'nullable|string|max:255',
            'no_hp'                => 'nullable|string|max:255',
            'module'               => 'nullable|string|max:255',
            'client'               => 'nullable|string|max:255',
            // Jika Jarvies sudah kirim email sendiri, kirim internet_message_id-nya
            // agar EcoSystem bisa link staging ke email tersebut (ambil graph_message_id + body)
            'internet_message_id'  => 'nullable|string|max:1000',
        ]);

        try {
            // Decode cc_emails jika dikirim sebagai JSON string
            if (isset($validated['cc_emails']) && is_string($validated['cc_emails'])) {
                $decoded = json_decode($validated['cc_emails'], true);
                if (is_array($decoded)) {
                    $validated['cc_emails'] = $decoded;
                }
            }

            $staging = $this->service->createFromWeb($validated, (int) $validated['customer_id']);

            Log::info('StagingTicketController@jarviesStore: staging created from JARVIES', [
                'staging_id'  => $staging->id,
                'customer_id' => $validated['customer_id'],
            ]);

            // ── Link ke email Jarvies (jika internet_message_id dikirim) ─────────
            // Jika Jarvies kirim internet_message_id → langsung link ke email tersebut.
            // Jika tidak → biarkan processSentItems() menemukannya saat Fetch Email berikutnya.
            // EcoSystem TIDAK lagi kirim email duplikat karena Jarvies sudah kirim sendiri.
            $internetMsgId = $validated['internet_message_id'] ?? null;
            if ($internetMsgId) {
                $this->linkStagingToEmail($staging, $internetMsgId);
            }

            return response()->json([
                'success' => true,
                'id'      => $staging->id,
                'message' => 'Staging ticket created successfully',
            ], 201);

        } catch (\Exception $e) {
            Log::error('StagingTicketController@jarviesStore: gagal simpan staging', [
                'error' => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit ticket',
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
        if (!in_array($roleId, array_merge([RoleId::ADMIN->value, RoleId::EMPLOYEE->value], RoleId::HELPDESK_GROUP), true)) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'ticket_type'     => 'required|string|in:Incident,Service Request,Change Request,Consult',
            'ticket_priority' => 'required|string|in:Very High,High,Medium,Low',
            // Scale opsional. Daftar value masih didiskusikan — `nullable|string|max:50`
            // membatasi panjang tapi tidak mengikat ke whitelist tertentu agar
            // mudah diubah saat opsi final disepakati.
            'scale'           => 'nullable|string|max:50',
        ]);

        $staging = StagingTicket::findOrFail($id);

        try {
            $ticketType     = $request->input('ticket_type');
            $ticketPriority = $request->input('ticket_priority');
            $scale          = $request->input('scale');
            $result         = $this->service->approve($staging, $sessionUser['id'], $ticketType, $ticketPriority, $scale);
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

            // Attach SLA record
            app(SlaService::class)->attachToTicket($ticket, $staging);

            // Kirim notifikasi balasan otomatis ke customer
            $this->sendApprovalNotification($staging, $ticket, $sessionUser, $firstMessage);

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
        } catch (\Throwable $e) {
            Log::error('StagingTicketController@approve: failed to approve', [
                'staging_id' => $id,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'file'       => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to validate ticket',
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
        if (!in_array($roleId, array_merge([RoleId::ADMIN->value, RoleId::EMPLOYEE->value], RoleId::HELPDESK_GROUP), true)) {
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
            Log::error('StagingTicketController@reject: failed to reject', [
                'staging_id' => $id,
                'error'      => $e->getMessage(),
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject ticket',
            ], 500);
        }
    }

    // ─── API: Email attachment list from Graph ────────────────────────────────

    /**
     * GET /api/staging-tickets/{id}/email-attachments
     * Mengembalikan daftar attachment non-inline dari email di Graph API
     * (menggunakan staging.graph_message_id).
     * Digunakan modal validasi agar admin bisa lihat/download attachment sebelum approve.
     */
    public function emailAttachments($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $staging = StagingTicket::findOrFail($id);

        if (!$staging->graph_message_id) {
            return response()->json(['success' => true, 'data' => []]);
        }

        try {
            $attachments = app(EmailController::class)
                ->listNonInlineAttachments($staging->graph_message_id);

            // Map ke format yang dipakai modal — tambahkan proxy URL per item
            // Gunakan API endpoint agar bisa diakses oleh Jarvies (bukan web route)
            $data = array_map(fn ($att) => [
                'id'            => $att['id'],
                'name'          => $att['name'],
                'size'          => $att['size'],
                'content_type'  => $att['contentType'] ?? $att['content_type'] ?? null,
                'is_inline'     => $att['isInline'] ?? false,
                'url'           => "/api/staging-tickets/{$id}/attachment-download?attId=" . urlencode($att['id']),
            ], $attachments);

            return response()->json(['success' => true, 'data' => $data]);
        } catch (\Exception $e) {
            Log::warning('StagingTicketController@emailAttachments: gagal fetch dari Graph', [
                'staging_id'       => $id,
                'graph_message_id' => $staging->graph_message_id,
                'error'            => $e->getMessage(),
            ]);
            return response()->json(['success' => true, 'data' => []]);
        }
    }

    // ─── API: Proxy download attachment dari Graph (untuk Jarvies & EcoSystem) ──

    /**
     * GET /api/staging-tickets/{id}/attachment-download?attId={graphAttachmentId}
     * Stream attachment langsung dari Graph API ke browser.
     * API endpoint — dapat diakses oleh Jarvies maupun EcoSystem.
     */
    public function emailAttachmentDownload(\Illuminate\Http\Request $request, $id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $attId = $request->query('attId');
        if (!$attId) {
            return response()->json(['success' => false, 'message' => 'Missing attId parameter'], 400);
        }

        $staging = StagingTicket::findOrFail($id);

        if (!$staging->graph_message_id) {
            return response()->json(['success' => false, 'message' => 'No email associated with this staging ticket'], 404);
        }

        try {
            $emailCtrl = app(EmailController::class);
            $token     = $emailCtrl->getAccessTokenPublic();
            $sender    = config('services.microsoft_graph.sender_email');
            $baseUrl   = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

            $response = Http::withToken($token)->get(
                "{$baseUrl}/users/{$sender}/messages/{$staging->graph_message_id}/attachments/{$attId}"
            );

            if (!$response->successful()) {
                return response()->json(['success' => false, 'message' => 'Attachment not found'], 404);
            }

            $data         = $response->json();
            $contentBytes = base64_decode($data['contentBytes'] ?? '');
            $contentType  = $data['contentType'] ?? 'application/octet-stream';
            $name         = $data['name'] ?? 'attachment';

            return response($contentBytes, 200, [
                'Content-Type'        => $contentType,
                'Content-Disposition' => 'inline; filename="' . $name . '"',
                'Content-Length'      => strlen($contentBytes),
            ]);
        } catch (\Exception $e) {
            Log::warning('StagingTicketController@emailAttachmentDownload: gagal', [
                'staging_id' => $id,
                'att_id'     => $attId,
                'error'      => $e->getMessage(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve attachment'], 500);
        }
    }

    // ─── Web: Proxy download email attachment dari Graph ─────────────────────

    /**
     * GET /staging-email-attachments/{stagingId}?attId={graphAttachmentId}
     * Stream attachment langsung dari Graph API ke browser.
     * attId dikirim sebagai query parameter (bukan path) agar karakter khusus (=, +) tidak rusak di URL.
     */
    public function proxyEmailAttachment(\Illuminate\Http\Request $request, $stagingId)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            abort(401, 'Authentication required. Please log in to access this resource.');
        }

        $attId = $request->query('attId');
        if (!$attId) {
            abort(400, 'Missing attId parameter.');
        }

        $staging = StagingTicket::findOrFail($stagingId);

        if (!$staging->graph_message_id) {
            abort(404, 'No email associated with this staging ticket.');
        }

        try {
            $emailCtrl = app(EmailController::class);
            $token     = $emailCtrl->getAccessTokenPublic();
            $sender    = config('services.microsoft_graph.sender_email');
            $baseUrl   = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

            $response = Http::withToken($token)->get(
                "{$baseUrl}/users/{$sender}/messages/{$staging->graph_message_id}/attachments/{$attId}"
            );

            if (!$response->successful()) {
                abort(404, 'Attachment not found.');
            }

            $data         = $response->json();
            $contentBytes = base64_decode($data['contentBytes'] ?? '');
            $contentType  = $data['contentType'] ?? 'application/octet-stream';
            $name         = $data['name'] ?? 'attachment';

            return response($contentBytes, 200, [
                'Content-Type'        => $contentType,
                'Content-Disposition' => 'inline; filename="' . $name . '"',
                'Content-Length'      => strlen($contentBytes),
            ]);
        } catch (\Exception $e) {
            abort(500, 'Failed to retrieve attachment.');
        }
    }

    // ─── API: Email body preview (resolve inline images as base64) ───────────

    /**
     * GET /api/staging-tickets/{id}/preview-body
     * Mengembalikan email_body_html dengan cid: inline images diganti base64 data URI.
     * Digunakan oleh modal staging agar gambar bisa ditampilkan sebelum ticket di-approve.
     */
    public function previewBody($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $staging = StagingTicket::findOrFail($id);
        $html    = $staging->email_body_html ?? '';

        // Fallback: jika email_body_html kosong tapi graph_message_id ada,
        // fetch body langsung dari Graph (untuk staging lama atau web tickets yang baru dapat graph_message_id)
        if (!$html && $staging->graph_message_id) {
            try {
                $sender   = config('services.microsoft_graph.sender_email');
                $emailCtrl = app(EmailController::class);
                $msg = $emailCtrl->graphGetPublic(
                    "/users/{$sender}/messages/{$staging->graph_message_id}",
                    ['$select' => 'body']
                );
                $html = $msg['body']['content'] ?? '';
                // Simpan ke DB agar request berikutnya tidak perlu fetch ulang
                if ($html) {
                    $staging->update(['email_body_html' => $html]);
                }
            } catch (\Exception $e) {
                Log::warning('StagingTicketController@previewBody: gagal fetch body dari Graph', [
                    'staging_id'       => $staging->id,
                    'graph_message_id' => $staging->graph_message_id,
                    'error'            => $e->getMessage(),
                ]);
            }
        }

        $needsResolve = $staging->graph_message_id && (
            str_contains($html, 'cid:') ||
            ($staging->has_attachments && preg_match('/\[[^\]]+\.(png|jpe?g|gif|bmp|webp)\]/i', $html))
        );
        if ($html && $needsResolve) {
            try {
                $html = app(\App\Http\Controllers\EmailController::class)
                    ->resolveInlineImagesAsDataUris($staging->graph_message_id, $html);
            } catch (\Exception $e) {
                Log::warning('StagingTicketController@previewBody: resolve images failed', [
                    'staging_id' => $staging->id,
                    'error'      => $e->getMessage(),
                ]);
            }
        }

        return response()->json(['success' => true, 'html' => $html]);
    }

    // ─── API: Statistics (untuk badge/notif admin) ────────────────────────────

    /**
     * GET /api/staging-tickets/statistics
     */
    public function statistics()
    {
        $sessionUser = session('user');
        if (!$sessionUser || !in_array($sessionUser['role']['id'], array_merge([RoleId::ADMIN->value, RoleId::EMPLOYEE->value], RoleId::HELPDESK_GROUP), true)) {
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
    private function sendApprovalNotification(StagingTicket $staging, Ticket $ticket, array $sessionUser, ?TicketMessage $firstMessage = null): void
    {
        try {
            // Pastikan PHP tidak timeout — Graph API bisa memakan 20-60 detik di server production
            set_time_limit(180);
            Log::info('StagingTicketController@sendApprovalNotification: mulai', [
                'staging_id'         => $staging->id,
                'ticket_id'          => $ticket->ticket_id,
                'channel'            => $staging->channel,
                'has_graph_msg'      => !empty($staging->graph_message_id),
                'submitted_by_email' => $staging->submitted_by_email,
            ]);

            // ── Ambil nama pengirim (helpdesk yang approve) ──
            $nickName = $sessionUser['nick_name'] ?? null;
            if (!$nickName) {
                $nickName = DB::table('employee_basic_data')
                    ->where('employee_id', $sessionUser['id'])
                    ->value('nick_name');
            }
            $signatureName = $nickName ?? explode(' ', $sessionUser['name'] ?? 'Helpdesk')[0];

            $ticketNumber = $ticket->ticket_number ?? '—';

            // Bangun detail tiket (metadata: phone, module, client, description)
            // agar email approval berisi isi tiket yang sama seperti tampilan Jarvies
            $detailRows = '';
            if (!empty($staging->no_hp))  $detailRows .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555;white-space:nowrap">Phone</td><td>' . e($staging->no_hp)  . '</td></tr>';
            if (!empty($staging->module)) $detailRows .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555;white-space:nowrap">Module</td><td>' . e($staging->module) . '</td></tr>';
            if (!empty($staging->client)) $detailRows .= '<tr><td style="padding:4px 12px 4px 0;font-weight:600;color:#555;white-space:nowrap">Client</td><td>' . e($staging->client) . '</td></tr>';

            $metaTable = $detailRows
                ? '<table style="border-collapse:collapse;margin-bottom:16px">' . $detailRows . '</table>'
                : '';

            $descSection = !empty($staging->body)
                ? '<div style="margin-bottom:16px"><strong>Description:</strong>'
                  . '<div style="margin-top:8px;padding:12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px">'
                  . $staging->body
                  . '</div></div>'
                : '';

            $detailBlock = ($metaTable || $descSection)
                ? '<div style="margin:16px 0;padding:16px;background:#f9fafb;border-left:4px solid #c62828;border-radius:4px">'
                  . $metaTable . $descSection
                  . '</div>'
                : '';

            $bodyPlain = "Baik akan disampaikan dengan Nomor Ticket {$ticketNumber}\n\nBest Regards,\n{$signatureName}";

            $bodyHtml  = "<p>Baik akan disampaikan dengan Nomor Ticket <strong>{$ticketNumber}</strong></p>"
                       . $detailBlock
                       . "<br><p>Best Regards,<br><strong>{$signatureName}</strong></p>";

            $safeNum  = htmlspecialchars($ticketNumber, ENT_QUOTES, 'UTF-8');
            $safeAgent = htmlspecialchars($signatureName, ENT_QUOTES, 'UTF-8');
            $safeDesc  = htmlspecialchars(mb_substr($staging->description ?? '', 0, 90), ENT_QUOTES, 'UTF-8');
            $bodyHtml  = <<<HTML
            <table width="100%" cellpadding="0" cellspacing="0" border="0"
                   style="font-family:Arial,Helvetica,sans-serif;max-width:600px;border-collapse:collapse;">
                <tr>
                    <td style="background-color:#8b1a1a;padding:16px 24px;border-radius:6px 6px 0 0;">
                        <p style="color:#ffffff;font-size:16px;font-weight:bold;margin:0;line-height:1.3;">PT Eclectic Consulting</p>
                        <p style="color:rgba(255,255,255,0.7);font-size:11px;margin:3px 0 0 0;">Helpdesk Support &nbsp;&middot;&nbsp; Ticket #{$safeNum}</p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#ffffff;padding:24px;border-left:1px solid #e5e7eb;border-right:1px solid #e5e7eb;font-size:14px;color:#374151;line-height:1.7;">
                        <p>Baik akan disampaikan dengan Nomor Ticket <strong>#{$safeNum}</strong></p>
                        <p>Best Regards,<br><strong>{$safeAgent}</strong></p>
                    </td>
                </tr>
                <tr>
                    <td style="background-color:#f9fafb;padding:14px 24px;border:1px solid #e5e7eb;border-top:none;border-radius:0 0 6px 6px;">
                        <p style="color:#9ca3af;font-size:11px;margin:0;line-height:1.6;">
                            Sent by <strong style="color:#6b7280;">{$safeAgent}</strong> &mdash; PT Eclectic Consulting<br>
                            Ticket: <strong style="color:#6b7280;">#{$safeNum}</strong> &mdash; {$safeDesc}
                        </p>
                    </td>
                </tr>
            </table>
            HTML;

            // ── Fetch forwarded attachments dari email asal (Graph) ────────────
            // Diunduh SEBELUM membangun bodyHtml agar nama file bisa ditampilkan
            // di section "Original Ticket Content" sekaligus dilampirkan ke email.
            $rawAttachments = [];
            Log::info('StagingTicketController@sendApprovalNotification: step fetch-attachments', [
                'staging_id'      => $staging->id,
                'graph_message_id'=> $staging->graph_message_id,
            ]);
            if ($staging->graph_message_id) {
                try {
                    $emailCtrl = app(EmailController::class);
                    $sender    = config('services.microsoft_graph.sender_email');
                    $token     = $emailCtrl->getAccessTokenPublic();
                    $baseUrl   = rtrim(config('services.microsoft_graph.base_url', 'https://graph.microsoft.com/v1.0'), '/');

                    $attList = $emailCtrl->listNonInlineAttachments($staging->graph_message_id);
                    foreach ($attList as $att) {
                        $attRes = \Illuminate\Support\Facades\Http::withToken($token)->get(
                            "{$baseUrl}/users/{$sender}/messages/{$staging->graph_message_id}/attachments/{$att['id']}"
                        );
                        if ($attRes->successful()) {
                            $data = $attRes->json();
                            if (!empty($data['contentBytes'])) {
                                $rawAttachments[] = [
                                    'name'  => $data['name'] ?? ($att['name'] ?? 'attachment'),
                                    'mime'  => $data['contentType'] ?? ($att['contentType'] ?? 'application/octet-stream'),
                                    'bytes' => base64_decode($data['contentBytes']),
                                ];
                            }
                        }
                    }
                } catch (\Exception $e) {
                    Log::warning('StagingTicketController@sendApprovalNotification: gagal fetch original attachments', [
                        'staging_id'       => $staging->id,
                        'graph_message_id' => $staging->graph_message_id,
                        'error'            => $e->getMessage(),
                    ]);
                    // Non-fatal: lanjut kirim email tanpa attachment asal
                }
            }

            // ── Sertakan konten asli tiket sebagai quoted section ─────────────
            // Prioritas: $firstMessage->message_html (sudah di-rewrite oleh
            // processAttachmentsForMessage dengan URL /storage/... yang dapat diakses
            // oleh browser EcoSystem dan email client).
            // Fallback #1: $staging->email_body_html + resolve inline images sebagai data URI.
            // Fallback #2: $staging->body (hindari jika mungkin — bisa berisi URL Jarvies
            // proxy yang tidak bisa diakses di konteks EcoSystem).
            $originalBody = null;
            if ($firstMessage && trim(strip_tags($firstMessage->message_html ?? '')) !== '') {
                $originalBody = $firstMessage->message_html;
            } elseif (!empty($staging->email_body_html) && $staging->graph_message_id) {
                try {
                    $originalBody = app(EmailController::class)
                        ->resolveInlineImagesAsDataUris($staging->graph_message_id, $staging->email_body_html);
                } catch (\Exception $e) {
                    $originalBody = $staging->email_body_html;
                }
            } else {
                $originalBody = $staging->body ?? null;
            }
            if ($originalBody && trim(strip_tags($originalBody)) !== '') {
                // Jadikan relative src/href URLs menjadi absolute agar gambar tampil di email client.
                // Email client tidak bisa resolve relative URL — harus menggunakan full domain.
                $appUrl = rtrim(config('app.url'), '/');
                $originalBody = preg_replace_callback(
                    '~((?:src|href)=")(/(?!/))([^"]*)~i',
                    fn($m) => $m[1] . $appUrl . '/' . $m[3],
                    $originalBody
                );

                // Bangun daftar nama file attachment untuk ditampilkan di body email
                $attNamesHtml = '';
                if (!empty($rawAttachments)) {
                    $items = '';
                    foreach ($rawAttachments as $att) {
                        $items .= '<li style="margin:2px 0;">'
                            . htmlspecialchars($att['name'] ?? 'attachment', ENT_QUOTES, 'UTF-8')
                            . '</li>';
                    }
                    $attNamesHtml = '<div style="margin-top:12px;font-size:13px;color:#374151;">'
                        . '<p style="margin:0 0 4px;font-weight:600;color:#6b7280;font-size:12px;">Attachments:</p>'
                        . '<ul style="margin:0;padding-left:20px;">' . $items . '</ul>'
                        . '</div>';
                }

                $bodyHtml .= <<<HTML

                <div style="margin-top:24px;padding-top:16px;border-top:2px solid #e5e7eb;font-family:Arial,Helvetica,sans-serif;font-size:13px;color:#6b7280;max-width:600px;">
                    <p style="margin:0 0 8px 0;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;color:#9ca3af;">Original Ticket Content</p>
                    <div style="border-left:3px solid #e5e7eb;padding:0 0 0 16px;color:#374151;font-size:14px;line-height:1.7;">
                        {$originalBody}
                        {$attNamesHtml}
                    </div>
                </div>
                HTML;
            }

            Log::info('StagingTicketController@sendApprovalNotification: step create-message', [
                'staging_id'      => $staging->id,
                'raw_att_count'   => count($rawAttachments),
            ]);

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

            Log::info('StagingTicketController@sendApprovalNotification: resolving customer email', [
                'staging_id'           => $staging->id,
                'ticket_id'            => $ticket->ticket_id,
                'submitted_by_email'   => $staging->submitted_by_email,
                'customer_email'       => $customerEmail,
                'email_message_id'     => $staging->email_message_id,
                'email_thread_id'      => $staging->email_thread_id,
            ]);

            Log::info('StagingTicketController@sendApprovalNotification: step send-email', [
                'staging_id'    => $staging->id,
                'customer_email'=> $customerEmail,
            ]);

            if ($customerEmail) {
                // Subject format: "Ticket #26040014: FIX BISA"
                $subject   = 'Ticket #' . $ticketNumber . ': ' . ($staging->description ?? 'Ticket Update');
                $inReplyTo = $staging->email_message_id; // null untuk web-only → buat thread baru
                $threadId  = $staging->email_thread_id;   // conversationId fallback

                // Ambil CC dari staging — handle keduanya: PHP array (normal) dan JSON string (legacy/double-encode)
                $rawCc  = $staging->cc_emails;
                if (is_string($rawCc)) {
                    $rawCc = json_decode($rawCc, true) ?? [];
                }
                $ccList = is_array($rawCc) ? $rawCc : [];

                Log::info('StagingTicketController@sendApprovalNotification: sending email', [
                    'to'          => $customerEmail,
                    'subject'     => $subject,
                    'in_reply_to' => $inReplyTo,
                    'thread_id'   => $threadId,
                    'cc_count'    => count($ccList),
                ]);

                $emailResult = app(EmailController::class)->sendTicketReply(
                    $customerEmail,
                    $subject,
                    $bodyHtml,
                    $inReplyTo,
                    [],               // files
                    $ccList,          // ccList dari staging
                    true,             // noRePrefix = true → subject tidak ditambah "Re:"
                    $threadId,        // conversationId fallback
                    false,            // forceNewDraft = false → pakai createReply agar Exchange auto-set
                    $rawAttachments   // forward attachment dari email [Menunggu Validasi] asal
                );

                // Simpan conversationId ke ticket agar reply berikutnya threaded ke email
                // approval ini. PENTING: selalu overwrite, bukan hanya saat kosong.
                //
                // Alasan: Exchange mengubah conversationId saat subject di-patch jadi
                // "Ticket #XXXX: ..." (tanpa prefix "Re:"). Original convId dari email
                // customer → BEDA dengan convId approval. Jika kita tidak update, subsequent
                // reply (dari Jarvies/EcoSystem) akan createReply pada convId lama → Gmail
                // lihat sebagai thread baru terpisah dari thread approval.
                $newConvId = $emailResult['conversation_id'] ?? null;
                if ($newConvId) {
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

                Log::info('StagingTicketController@sendApprovalNotification: email terkirim', [
                    'ticket_id'          => $ticket->ticket_id,
                    'internet_message_id'=> $internetMsgId,
                    'conversation_id'    => $newConvId,
                ]);
            } else {
                Log::warning('StagingTicketController@sendApprovalNotification: customer email tidak ditemukan, email dilewati', [
                    'staging_id'  => $staging->id,
                    'ticket_id'   => $ticket->ticket_id,
                    'customer_id' => $ticket->customer_id,
                ]);
            }

            Log::info('StagingTicketController@sendApprovalNotification: selesai', [
                'ticket_id'      => $ticket->ticket_id,
                'ticket_number'  => $ticketNumber,
                'email_sent'     => $customerEmail !== null,
            ]);

        } catch (\Throwable $e) {
            Log::error('StagingTicketController@sendApprovalNotification: GAGAL', [
                'staging_id' => $staging->id,
                'ticket_id'  => $ticket->ticket_id,
                'error'      => $e->getMessage(),
                'class'      => get_class($e),
                'file'       => $e->getFile() . ':' . $e->getLine(),
                'trace'      => array_slice(
                    array_map(fn($f) => ($f['file'] ?? '?') . ':' . ($f['line'] ?? '?') . ' ' . ($f['function'] ?? ''),
                    $e->getTrace()), 0, 8
                ),
            ]);
            // Tidak throw — gagal notifikasi tidak membatalkan approval yang sudah berhasil
        }
    }

    // ─── Private: Email konfirmasi saat customer submit tiket ───────────────

    /**
     * Kirim email konfirmasi ke customer setelah staging ticket dibuat dari Jarvies.
     *
     * Email dikirim dari M365 helpdesk (Raditya) ke submitted_by_email customer.
     * Attachment dari form turut dilampirkan agar bisa diakses via Graph API
     * saat admin melakukan validasi staging ticket.
     *
     * Hasil pengiriman (conversation_id, graph_message_id, internet_message_id)
     * disimpan ke staging agar approval nanti bisa thread ke email ini.
     *
     * Non-fatal: gagal kirim email tidak membatalkan pembuatan staging.
     */
    private function sendSubmissionEmail(StagingTicket $staging, array $files, array $validated): void
    {
        $toEmail = $staging->submitted_by_email ?? ($validated['submitted_by_email'] ?? null);
        if (!$toEmail) {
            Log::info('StagingTicketController@sendSubmissionEmail: skip — no customer email', [
                'staging_id' => $staging->id,
            ]);
            return;
        }

        try {
            $subject  = '[No Reply] [Menunggu Validasi] ' . $staging->description;
            $bodyHtml = $this->buildStagingEmailBody($staging);

            // Decode cc_emails dari staging (sudah di-decode di awal jarviesStore)
            $rawCc  = $staging->cc_emails;
            if (is_string($rawCc)) {
                $rawCc = json_decode($rawCc, true) ?? [];
            }
            $ccList = is_array($rawCc) ? $rawCc : [];

            $emailResult = app(EmailController::class)->sendTicketReply(
                $toEmail,
                $subject,
                $bodyHtml,
                null,    // $inReplyTo — email baru, bukan reply
                $files,  // attachment dari form (UploadedFile[])
                $ccList,
                true,    // $noRePrefix — pakai subject apa adanya (sudah ada [PENDING])
                null     // $threadId
            );

            // Simpan identitas email ke staging agar approval bisa thread ke sini.
            // Ubah channel ke 'email' supaya modal validasi menampilkan email_body_html
            // (termasuk inline images dan attachment dari Graph) alih-alih body teks biasa.
            $staging->update([
                'email_thread_id'  => $emailResult['conversation_id']     ?? null,
                'graph_message_id' => $emailResult['graph_message_id']    ?? null,
                'email_message_id' => $emailResult['internet_message_id'] ?? null,
                'channel'          => 'email',
                'email_body_html'  => $bodyHtml,
            ]);

            Log::info('StagingTicketController@sendSubmissionEmail: email terkirim', [
                'staging_id'          => $staging->id,
                'to'                  => $toEmail,
                'internet_message_id' => $emailResult['internet_message_id'] ?? null,
            ]);

        } catch (\Exception $e) {
            Log::warning('StagingTicketController@sendSubmissionEmail: gagal kirim email', [
                'staging_id' => $staging->id,
                'to'         => $toEmail,
                'error'      => $e->getMessage(),
            ]);
            // Tidak throw — gagal email tidak membatalkan staging yang sudah dibuat
        }
    }

    /**
     * Link staging ticket ke email yang sudah dikirim Jarvies.
     *
     * Jarvies mengirim emailnya sendiri (subject "[Menunggu Validasi]") dan
     * mengoper internetMessageId ke API ini. EcoSystem mencari email tersebut
     * di Sent Items M365, lalu menyimpan graph_message_id, conversation_id,
     * email_body_html, dan mengubah channel ke 'email'.
     *
     * Setelah ini, modal validasi dapat menampilkan body email asli + attachment
     * yang terlampir di email tersebut via Graph API.
     *
     * Non-fatal: gagal link tidak membatalkan staging yang sudah dibuat.
     */
    private function linkStagingToEmail(StagingTicket $staging, string $internetMessageId): void
    {
        try {
            $sender    = config('services.microsoft_graph.sender_email');
            $emailCtrl = app(EmailController::class);
            $filterVal = str_replace("'", "''", $internetMessageId);

            // Cari di Sent Items (paling mungkin) lalu fallback global
            $graphMsgId     = null;
            $conversationId = null;
            foreach ([
                "/users/{$sender}/mailFolders/SentItems/messages",
                "/users/{$sender}/messages",
            ] as $path) {
                try {
                    $result = $emailCtrl->graphGetPublic($path, [
                        '$filter' => "internetMessageId eq '{$filterVal}'",
                        '$select' => 'id,conversationId',
                        '$top'    => 1,
                    ]);
                    if (!empty($result['value'][0]['id'])) {
                        $graphMsgId     = $result['value'][0]['id'];
                        $conversationId = $result['value'][0]['conversationId'] ?? null;
                        break;
                    }
                } catch (\Exception) {
                    // lanjut ke fallback berikutnya
                }
            }

            if (!$graphMsgId) {
                Log::warning('StagingTicketController@linkStagingToEmail: pesan tidak ditemukan di Graph', [
                    'staging_id'          => $staging->id,
                    'internet_message_id' => $internetMessageId,
                ]);
                return;
            }

            // Fetch body email untuk ditampilkan di modal validasi
            $emailBodyHtml = null;
            try {
                $msg           = $emailCtrl->graphGetPublic(
                    "/users/{$sender}/messages/{$graphMsgId}",
                    ['$select' => 'body']
                );
                $emailBodyHtml = $msg['body']['content'] ?? null;
            } catch (\Exception $e) {
                Log::warning('StagingTicketController@linkStagingToEmail: gagal fetch body', [
                    'staging_id' => $staging->id,
                    'error'      => $e->getMessage(),
                ]);
            }

            $update = [
                'graph_message_id' => $graphMsgId,
                'email_thread_id'  => $conversationId,
                'email_message_id' => $internetMessageId,
                'channel'          => 'email',
                'has_attachments'  => true,   // Jarvies sudah lampirkan file di emailnya
            ];
            if ($emailBodyHtml) {
                $update['email_body_html'] = $emailBodyHtml;
            }
            $staging->update($update);

            Log::info('StagingTicketController@linkStagingToEmail: berhasil link ke email', [
                'staging_id'          => $staging->id,
                'graph_message_id'    => $graphMsgId,
                'internet_message_id' => $internetMessageId,
            ]);

        } catch (\Exception $e) {
            Log::warning('StagingTicketController@linkStagingToEmail: gagal', [
                'staging_id'          => $staging->id,
                'internet_message_id' => $internetMessageId,
                'error'               => $e->getMessage(),
            ]);
        }
    }

    /**
     * Bangun HTML body untuk email konfirmasi submission ke customer.
     */
    private function buildStagingEmailBody(StagingTicket $staging): string
    {
        $rows = '';
        $rows .= $this->optionalRow('Subject', $staging->description);
        $rows .= $this->optionalRow('Priority', $staging->ticket_priority);
        $rows .= $this->optionalRow('Module', $staging->module);
        $rows .= $this->optionalRow('Client', $staging->client);
        $rows .= $this->optionalRow('Contact', $staging->name);
        $rows .= $this->optionalRow('Phone', $staging->no_hp);

        $table = $rows
            ? "<table style='border-collapse:collapse;width:100%;margin-bottom:16px'>{$rows}</table>"
            : '';

        $bodySection = '';
        if (!empty($staging->body)) {
            $bodySection = "<div style='margin-bottom:16px'><strong>Description:</strong><div style='margin-top:8px;padding:12px;background:#f9f9f9;border:1px solid #e0e0e0;border-radius:4px'>{$staging->body}</div></div>";
        }

        return "
<p>Dear Customer,</p>
<p>Your support ticket has been submitted and is currently under review. Our helpdesk team will process it shortly.</p>
{$table}
{$bodySection}
<p>We will notify you once your ticket has been approved and assigned a ticket number.</p>
<br>
<p>Best Regards,<br><strong>Helpdesk Support</strong></p>
";
    }

    /**
     * Bangun satu baris &lt;tr&gt; tabel untuk email, hanya jika $value tidak kosong.
     */
    private function optionalRow(string $label, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        return "<tr>"
            . "<td style='padding:6px 12px 6px 0;font-weight:600;white-space:nowrap;vertical-align:top;color:#555'>{$label}</td>"
            . "<td style='padding:6px 0'>: " . e($value) . "</td>"
            . "</tr>";
    }

    // ─── Private formatter ────────────────────────────────────────────────────

    private function formatStaging(StagingTicket $s): array
    {
        $customerName = null;
        if ($s->customer) {
            $bd = $s->customer->basicData;
            $customerName = $bd ? trim(($bd->title ?? '') . ' ' . ($bd->name_1 ?? '')) : null;
        }

        $endCustomerName = null;
        if ($s->end_customer_id && $s->relationLoaded('endCustomer') && $s->endCustomer) {
            $bd = $s->endCustomer->basicData;
            $endCustomerName = $bd ? trim(($bd->title ?? '') . ' ' . ($bd->name_1 ?? '')) : null;
        }

        $validatorName = null;
        if ($s->validator) {
            $bd = $s->validator->basicData;
            $validatorName = $bd ? trim(($bd->first_name ?? '') . ' ' . ($bd->last_name ?? '')) : null;
        }

        // Attachments (dari staging_attachments — web/Jarvies uploads)
        $attachments = [];
        if ($s->relationLoaded('attachments')) {
            $attachments = $s->attachments->map(fn ($a) => [
                'id'            => $a->id,
                'original_name' => $a->original_name,
                'file_name'     => $a->file_name,
                'file_size'     => $a->file_size,
                'mime_type'     => $a->mime_type,
                'url'           => $a->public_url,
            ])->toArray();
        }

        return [
            'id'                  => $s->id,
            'customer_id'         => $s->customer_id,
            'customer_name'       => $customerName,
            'end_customer_id'     => $s->end_customer_id,
            'end_customer_name'   => $endCustomerName,
            'submitted_by_email'  => $s->submitted_by_email,
            'sender_name'         => $s->sender_name,
            'cc_emails'           => $s->cc_emails,
            'description'         => $s->description,
            'body'                => $s->body,           // ← full message body dari Jarvies/web form
            'ticket_priority'     => $s->ticket?->ticket_priority ?? $s->ticket_priority,
            'ticket_type'         => $s->ticket?->ticket_type,
            'scale'               => $s->ticket?->scale ?? $s->scale,
            'status'              => $s->status,
            'rejection_reason'    => $s->rejection_reason,
            'channel'             => $s->channel,
            'email_thread_id'     => $s->email_thread_id,
            'email_body_html'     => $s->email_body_html,
            'has_attachments'     => $s->has_attachments || count($attachments) > 0,
            'graph_message_id'    => $s->graph_message_id,
            'validated_by'        => $s->validated_by,
            'validator_name'      => $validatorName,
            'validated_at'        => $s->validated_at?->toIso8601String(),
            'ticket_id'           => $s->ticket_id,
            'ticket_number'       => $s->ticket?->ticket_number,
            'created_at'          => $s->created_at?->toIso8601String(),
            'attachments'         => $attachments,       // ← file attachments (web uploads)
            // Field tambahan
            'name'                => $s->name,
            'no_hp'               => $s->no_hp,
            'module'              => $s->module,
            'client'              => $s->client,
        ];
    }
}
