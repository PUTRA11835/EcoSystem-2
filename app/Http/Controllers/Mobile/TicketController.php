<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\TicketDetailResource;
use App\Http\Resources\Mobile\TicketListResource;
use App\Http\Resources\Mobile\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile Ticket Controller
 *
 * Semua endpoint di sini dilindungi oleh middleware `mobile.employee`
 * (Bearer token Sanctum, hanya untuk employee).
 *
 * Auth user didapat dari $request->user() → instance AuthUser
 * AuthUser->employee_id → ID employee yang sedang login.
 */
class TicketController extends Controller
{
    // =========================================================================
    // GET /api/mobile/employee/tickets
    // =========================================================================

    /**
     * List tiket dengan stats ringkasan.
     *
     * Query params:
     *   ?search=          string  — cari berdasarkan subject/description/nama customer
     *   ?status=          string  — Open | In Progress | Hold | Reply | Closed
     *   ?assigned_to_me=  boolean — true: hanya tiket yang di-assign ke user login
     *   ?page=            integer — halaman (default 1, per halaman 15)
     */
    public function index(Request $request)
    {
        // ----- Stats global (tidak terpengaruh filter) -----
        $stats = DB::table('ticket')
            ->whereNull('deleted_at')
            ->selectRaw("
                COUNT(*) as total,
                SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                SUM(CASE WHEN status = 'hold'        THEN 1 ELSE 0 END) as hold,
                SUM(CASE WHEN status = 'closed'      THEN 1 ELSE 0 END) as closed
            ")
            ->first();

        // ----- Query tiket dengan eager loading -----
        $query = Ticket::with(['customer.basicData', 'employee.basicData'])
            ->whereNull('deleted_at');

        // Filter: hanya tiket milik user yang login
        if ($request->boolean('assigned_to_me')) {
            $query->where('employee_id', $request->user()->employee_id);
        }

        // Filter: search (subject, description, atau nama customer)
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhereHas('customer.basicData', fn($cq) =>
                      $cq->where('name_1', 'like', "%{$search}%")
                  );
            });
        }

        // Filter: status (mobile label → DB value)
        if ($status = $request->query('status')) {
            $dbStatus = $this->mapStatusToDb($status);
            if ($dbStatus) {
                $query->where('status', $dbStatus);
            }
        }

        $tickets = $query->orderBy('updated_at', 'desc')->paginate(15);

        return response()->json([
            'success' => true,
            'stats'   => [
                'total'       => (int) $stats->total,
                'in_progress' => (int) $stats->in_progress,
                'hold'        => (int) $stats->hold,
                'closed'      => (int) $stats->closed,
            ],
            'data'    => TicketListResource::collection($tickets->items()),
            'meta'    => [
                'current_page' => $tickets->currentPage(),
                'last_page'    => $tickets->lastPage(),
                'total'        => $tickets->total(),
            ],
        ]);
    }

    // =========================================================================
    // GET /api/mobile/employee/tickets/{id}
    // =========================================================================

    public function show($id)
    {
        $ticket = Ticket::with([
            'customer.basicData',
            'employee.basicData',
            'members.basicData',
        ])->where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();

        return response()->json([
            'success' => true,
            'data'    => new TicketDetailResource($ticket),
        ]);
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets
    // =========================================================================

    /**
     * Buat tiket baru dari mobile.
     * Content-Type: multipart/form-data (karena ada optional file upload).
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'title'       => 'required|string|max:255',
            'description' => 'required|string',
            'type'        => 'required|in:Bug,Feature Request,Improvement,Question',
            'priority'    => 'required|in:Low,Medium,High',
            'attachment'  => 'nullable|file|mimes:pdf,png,jpg,jpeg|max:10240',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $authUser     = $request->user();
        $ticketNumber = 'TKT-' . strtoupper(substr(uniqid(), -8)) . '-' . now()->format('Ymd');

        $ticket = Ticket::create([
            'ticket_number'   => $ticketNumber,
            'subject'         => $request->title,
            'description'     => $request->description,
            'category'        => $request->type,
            'ticket_priority' => $request->priority,
            'status'          => 'open',
            'employee_id'     => $authUser->employee_id,
            'channel'         => 'web',
        ]);

        // Handle file upload (opsional)
        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = $file->store('ticket-attachments/' . $ticket->ticket_id, 'public');

            TicketAttachment::create([
                'ticket_id'        => $ticket->ticket_id,
                'uploaded_by_type' => 'employee',
                'uploaded_by_id'   => $authUser->employee_id,
                'attachment_type'  => 'file',
                'link_url'         => $path,
                'link_title'       => $file->getClientOriginalName(),
            ]);
        }

        $ticket->load(['customer.basicData', 'employee.basicData']);

        return response()->json([
            'success' => true,
            'message' => 'Tiket berhasil dibuat.',
            'data'    => new TicketDetailResource($ticket),
        ], 201);
    }

    // =========================================================================
    // GET /api/mobile/employee/tickets/{id}/messages
    // =========================================================================

    public function getMessages($id)
    {
        $ticket = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();

        $messages = TicketMessage::where('ticket_id', $ticket->ticket_id)
            ->where('is_internal_note', false)
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'data'    => TicketMessageResource::collection($messages),
        ]);
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets/{id}/messages
    // =========================================================================

    public function sendMessage(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'content' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket   = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
        $authUser = $request->user();

        // Ambil nama lengkap employee dari basic_data
        $senderName = DB::table('employee_basic_data')
            ->where('employee_id', $authUser->employee_id)
            ->selectRaw("TRIM(CONCAT(first_name, ' ', COALESCE(last_name, ''))) as full_name")
            ->value('full_name') ?? $authUser->email;

        $message = TicketMessage::create([
            'ticket_id'           => $ticket->ticket_id,
            'sender_type'         => 'employee',
            'sender_id'           => $authUser->employee_id,
            'sender_name'         => $senderName,
            'message'             => $request->content,
            'is_internal_note'    => false,
            'channel'             => 'web',
            'is_read_by_agent'    => true,
            'is_read_by_customer' => false,
        ]);

        $ticket->update([
            'last_message_at'    => now(),
            'last_agent_reply_at'=> now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Pesan berhasil dikirim.',
            'data'    => new TicketMessageResource($message),
        ], 201);
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets/{id}/ownership
    // =========================================================================

    /**
     * Ambil alih kepemilikan tiket (assign ke user yang login).
     */
    public function takeOwnership(Request $request, $id)
    {
        $ticket   = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
        $authUser = $request->user();

        $ticket->update(['employee_id' => $authUser->employee_id]);
        $ticket->load(['customer.basicData', 'employee.basicData', 'members.basicData']);

        return response()->json([
            'success' => true,
            'message' => 'Tiket berhasil diambil alih.',
            'data'    => new TicketDetailResource($ticket),
        ]);
    }

    // =========================================================================
    // PUT /api/mobile/employee/tickets/{id}/mandays
    // =========================================================================

    public function updateMandays(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'man_days' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validasi gagal.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $ticket = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
        $ticket->update(['man_days' => $request->man_days]);

        return response()->json([
            'success' => true,
            'message' => 'Mandays berhasil diperbarui.',
            'data'    => ['man_days' => (float) $ticket->man_days],
        ]);
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets/{id}/send-to-customer
    // =========================================================================

    /**
     * Ubah status tiket menjadi "reply" dan catat timestamp terakhir balasan agent.
     * Notifikasi ke customer dapat ditambahkan di sini (email/push notification).
     */
    public function sendToCustomer(Request $request, $id)
    {
        $ticket = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();

        $ticket->update([
            'status'              => 'reply',
            'last_agent_reply_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Notifikasi berhasil dikirim ke customer.',
        ]);
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

    /**
     * Map label status dari mobile ke nilai DB.
     *
     * Mobile label  → DB value
     * "Open"        → open
     * "In Progress" → in_progress
     * "Hold"        → hold
     * "Reply"       → reply
     * "Closed"      → closed
     */
    private function mapStatusToDb(string $status): ?string
    {
        return match (strtolower(trim($status))) {
            'open'        => 'open',
            'in progress' => 'in_progress',
            'hold'        => 'hold',
            'reply'       => 'reply',
            'closed'      => 'closed',
            default       => null,
        };
    }
}
