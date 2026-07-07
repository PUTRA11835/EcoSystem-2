<?php

namespace App\Http\Controllers\Lite;

use App\Enums\RoleId;
use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class LiteTicketController extends Controller
{
    /**
     * Daftar tiket — role-based filtering, dengan pagination.
     * Adaptasi dari TicketController::index() untuk Lite API.
     *
     * GET /api/lite/tickets
     */
    public function index(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $roleId     = (int) ($user['role']['id'] ?? 0);
            $employeeId = (int) $user['id'];
            $perPage    = min((int) ($request->query('per_page', 20)), 100);
            $page       = max((int) ($request->query('page', 1)), 1);
            $status     = $request->query('status');
            $priority   = $request->query('priority');
            $search     = $request->query('search');

            $query = Ticket::with(['customer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                ->whereNull('is_hidden');

            // ── Scope berdasarkan role ────────────────────────────────────────
            if (strtolower($user['employee_type'] ?? 'internal') === 'external'
                && $roleId !== RoleId::EC_ADMINISTRATOR->value) {
                // External employee: hanya tiket yang dia tangani
                $query->where(function ($q) use ($employeeId) {
                    $q->where('ticket_lead_id', $employeeId)
                      ->orWhereHas('members', fn ($i) => $i->where('ticket_member.employee_id', $employeeId));
                });
            } elseif ($roleId === RoleId::DELIVERY_SUPPORT_USER->value) {
                // DS User: hanya tiket belum di-assign (unassigned) — sesuai aplikasi utama
                $query->whereNull('ticket_lead_id');
            }
            // Admin, Head, Helpdesk, Manager: lihat semua tiket

            // ── Filter ───────────────────────────────────────────────────────
            if ($status) {
                $query->where('status', $status);
            }
            if ($priority) {
                $query->where('ticket_priority', $priority);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ticket_number', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $query->orderByRaw('COALESCE(last_message_at, created_at) DESC');

            $paginated = $query->paginate($perPage, ['*'], 'page', $page);

            $ticketsData = collect($paginated->items())->map(fn ($ticket) => $this->formatTicket($ticket));

            return response()->json([
                'success' => true,
                'data'    => $ticketsData,
                'meta'    => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@index error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve tickets.'], 500);
        }
    }

    /**
     * Detail satu tiket.
     *
     * GET /api/lite/tickets/{id}
     */
    public function show(Request $request, int $id)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $ticket = Ticket::with([
                'customer.basicData',
                'endCustomer.basicData',
                'ticketLead.basicData',
                'members.basicData',
                'sla.policy',
            ])->find($id);

            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            if ($ticket->is_hidden && (int) ($user['role']['id'] ?? 0) !== RoleId::EC_ADMINISTRATOR->value) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            return response()->json([
                'success' => true,
                'data'    => $this->formatTicketDetail($ticket),
            ]);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@show error', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve ticket.'], 500);
        }
    }

    /**
     * Daftar pesan untuk satu tiket.
     * Adaptasi dari TicketMessageController::index().
     *
     * Query param `highlight_message_id` opsional — dipakai saat user membuka
     * tiket ini dari notifikasi (mis. di-tag di internal note). Frontend tidak
     * perlu mencari sendiri pesan mana yang harus di-scroll: backend menandai
     * pesan tsb dengan `is_highlighted: true` pada tiap item, dan menggemakan
     * id-nya di `meta.highlight_message_id` untuk validasi (null jika pesan
     * tidak ditemukan/bukan milik tiket ini).
     *
     * GET /api/lite/tickets/{ticketId}/messages
     * GET /api/lite/tickets/{ticketId}/messages?highlight_message_id=500
     */
    public function getMessages(Request $request, int $ticketId)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $ticket = Ticket::find($ticketId);
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $highlightMessageId = $request->query('highlight_message_id');
            $highlightMessageId = $highlightMessageId !== null ? (int) $highlightMessageId : null;

            $messages = TicketMessage::with(['attachments', 'replyTo'])
                ->where('ticket_id', $ticketId)
                ->orderBy('created_at', 'asc')
                ->get();

            // Validasi: pesan yang mau di-highlight harus benar-benar ada di tiket ini.
            if ($highlightMessageId !== null && !$messages->contains('id', $highlightMessageId)) {
                $highlightMessageId = null;
            }

            $formatted = $messages->map(fn ($msg) => $this->formatMessage($msg, $ticket, $highlightMessageId));

            return response()->json([
                'success' => true,
                'data'    => $formatted,
                'meta'    => [
                    'highlight_message_id' => $highlightMessageId,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@getMessages error', ['error' => $e->getMessage(), 'ticket_id' => $ticketId]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve messages.'], 500);
        }
    }

    /**
     * Tambah pesan ke tiket (internal note atau reply).
     * Hanya mendukung pesan teks/HTML — tidak termasuk integrasi email.
     *
     * POST /api/lite/tickets/{ticketId}/messages
     */
    public function addMessage(Request $request, int $ticketId)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $ticket = Ticket::find($ticketId);
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $validator = Validator::make($request->all(), [
                'message'          => 'required|string|min:1',
                'message_type'     => 'nullable|in:reply,internal_note',
                'is_internal_note' => 'nullable|boolean',
                'reply_to_id'      => 'nullable|integer|exists:ticket_messages,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $isInternalNote = $request->boolean('is_internal_note')
                || $request->input('message_type') === 'internal_note';

            $message = TicketMessage::create([
                'ticket_id'        => $ticketId,
                'sender_type'      => 'employee',
                'sender_id'        => $user['id'],
                'sender_name'      => $user['name'],
                'sender_email'     => $user['email'] ?? null,
                'message'          => strip_tags($request->message),
                'message_html'     => $request->message,
                'message_type'     => $isInternalNote ? 'internal_note' : 'reply',
                'is_internal_note' => $isInternalNote,
                'reply_to_id'      => $request->reply_to_id,
                'channel'          => 'web',
                'created_at'       => now(),
                'updated_at'       => now(),
            ]);

            // Update last_message_at pada tiket
            $ticket->update(['last_message_at' => now()]);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'data'    => $this->formatMessage($message->fresh(['attachments', 'replyTo']), $ticket),
            ], 201);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@addMessage error', ['error' => $e->getMessage(), 'ticket_id' => $ticketId]);
            return response()->json(['success' => false, 'message' => 'Failed to send message.'], 500);
        }
    }

    /**
     * Update status tiket.
     * Hanya role yang berwenang (Admin, Helpdesk, Head, Support Manager) yang dapat mengubah status.
     *
     * PATCH /api/lite/tickets/{id}/status
     */
    public function updateStatus(Request $request, int $id)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $roleId = (int) ($user['role']['id'] ?? 0);

            $allowedRoles = array_merge(
                RoleId::TICKET_MANAGER_GROUP,
                [RoleId::DELIVERY_SUPPORT_MANAGER->value]
            );

            if (!in_array($roleId, $allowedRoles, true)) {
                return response()->json([
                    'success' => false,
                    'message' => 'You do not have permission to update ticket status.',
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'status' => 'required|in:open,inprocess,waiting_on_customer,waiting_on_3rd_party,waiting_to_confirmation,hold,cancelled,closed',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $ticket = Ticket::find($id);
            if (!$ticket) {
                return response()->json(['success' => false, 'message' => 'Ticket not found.'], 404);
            }

            $oldStatus = $ticket->status;
            $newStatus = $request->status;

            $ticket->update([
                'status'     => $newStatus,
                'updated_at' => now(),
            ]);

            Log::info('LiteTicketController: status updated', [
                'ticket_id'  => $id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'updated_by' => $user['id'],
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully.',
                'data'    => [
                    'ticket_id'  => $ticket->ticket_id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@updateStatus error', ['error' => $e->getMessage(), 'id' => $id]);
            return response()->json(['success' => false, 'message' => 'Failed to update ticket status.'], 500);
        }
    }

    /**
     * Daftar tiket milik user yang login (sebagai PIC atau member), semua role.
     * Scope sama dengan ticket_stats di GET /dashboard untuk Delivery Support User.
     *
     * GET /api/lite/tickets/my
     */
    public function myTickets(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $employeeId = (int) $user['id'];
            $perPage    = min((int) ($request->query('per_page', 20)), 100);
            $page       = max((int) ($request->query('page', 1)), 1);
            $status     = $request->query('status');
            $priority   = $request->query('priority');
            $search     = $request->query('search');

            $query = Ticket::with(['customer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy'])
                ->whereNull('is_hidden')
                ->where(function ($q) use ($employeeId) {
                    $q->where('ticket_lead_id', $employeeId)
                      ->orWhereHas('members', fn ($i) => $i->where('ticket_member.employee_id', $employeeId));
                });

            if ($status) {
                $query->where('status', $status);
            }
            if ($priority) {
                $query->where('ticket_priority', $priority);
            }
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('ticket_number', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%");
                });
            }

            $query->orderByRaw('COALESCE(last_message_at, created_at) DESC');

            $paginated   = $query->paginate($perPage, ['*'], 'page', $page);
            $ticketsData = collect($paginated->items())->map(fn ($ticket) => $this->formatTicket($ticket));

            return response()->json([
                'success' => true,
                'data'    => $ticketsData,
                'meta'    => [
                    'current_page' => $paginated->currentPage(),
                    'per_page'     => $paginated->perPage(),
                    'total'        => $paginated->total(),
                    'last_page'    => $paginated->lastPage(),
                ],
            ]);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@myTickets error', ['error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to retrieve tickets.'], 500);
        }
    }

    /**
     * Statistik tiket — ringkasan per status.
     *
     * GET /api/lite/tickets/statistics
     */
    public function statistics(Request $request)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $base = DB::table('ticket')->whereNull('deleted_at')->whereNull('is_hidden');

            $stats = [
                'total'                   => (clone $base)->count(),
                'open'                    => (clone $base)->where('status', 'open')->count(),
                'inprocess'               => (clone $base)->where('status', 'inprocess')->count(),
                'waiting_on_customer'     => (clone $base)->where('status', 'waiting_on_customer')->count(),
                'waiting_on_3rd_party'    => (clone $base)->where('status', 'waiting_on_3rd_party')->count(),
                'waiting_to_confirmation' => (clone $base)->where('status', 'waiting_to_confirmation')->count(),
                'hold'                    => (clone $base)->where('status', 'hold')->count(),
                'cancelled'               => (clone $base)->where('status', 'cancelled')->count(),
                'closed'                  => (clone $base)->where('status', 'closed')->count(),
            ];

            return response()->json(['success' => true, 'data' => $stats]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'message' => 'Failed to retrieve statistics.'], 500);
        }
    }

    // =========================================================================
    // PRIVATE HELPERS
    // =========================================================================

    private function resolveUser(Request $request): ?array
    {
        return $request->session()->get('user')
            ?? $request->attributes->get('lite_user');
    }

    /** Format satu tiket untuk respons list */
    private function formatTicket(Ticket $ticket): array
    {
        return [
            'ticket_id'       => $ticket->ticket_id,
            'ticket_number'   => $ticket->ticket_number,
            'description'     => $ticket->description,
            'ticket_priority' => $ticket->ticket_priority,
            'ticket_type'     => $ticket->ticket_type,
            'status'          => $ticket->status,
            'start_date'      => $ticket->start_date,
            'end_date'        => $ticket->end_date,
            'last_message_at' => $ticket->last_message_at,
            'created_at'      => $ticket->created_at,
            'updated_at'      => $ticket->updated_at,
            'customer'        => $ticket->customer ? [
                'customer_id'   => $ticket->customer->customer_id,
                'customer_name' => $ticket->customer->basicData->name_1 ?? null,
                'customer_code' => $ticket->customer->customer_code,
            ] : null,
            'pic' => $ticket->ticketLead ? [
                'employee_id'   => $ticket->ticketLead->employee_id,
                'employee_name' => $ticket->ticketLead->basicData->nick_name
                    ?? $ticket->ticketLead->basicData->first_name ?? 'Unknown',
            ] : null,
            'members' => $ticket->members->map(fn ($m) => [
                'employee_id'   => $m->employee_id,
                'employee_name' => $m->basicData->nick_name ?? $m->basicData->first_name ?? 'Unknown',
            ]),
            'sla' => $ticket->sla ? [
                'resolution_status' => $ticket->sla->resolution_status,
                'resolution_due_at' => $ticket->sla->resolution_due_at,
                'response_status'   => $ticket->sla->response_status,
            ] : null,
        ];
    }

    /** Format tiket dengan detail lengkap (untuk show endpoint) */
    private function formatTicketDetail(Ticket $ticket): array
    {
        return array_merge($this->formatTicket($ticket), [
            'scale'                    => $ticket->scale,
            'channel'                  => $ticket->channel,
            'man_days'                 => $ticket->man_days,
            'progress_percentage'      => (float) ($ticket->progress_percentage ?? 0),
            'wait_close'               => $ticket->wait_close,
            'last_customer_reply_at'   => $ticket->last_customer_reply_at,
            'last_agent_reply_at'      => $ticket->last_agent_reply_at,
            'end_customer_id'          => $ticket->end_customer_id,
            'end_customer_name'        => $ticket->endCustomer?->basicData?->name_1,
            'sla_detail' => $ticket->sla ? [
                'target_response_hours'   => $ticket->sla->policy?->response_hours,
                'response_time_hours'     => $ticket->sla->validation_duration_hours,
                'response_status'         => $ticket->sla->response_status,
                'target_resolution_hours' => $ticket->sla->policy?->resolution_hours,
                'resolution_due_at'       => $ticket->sla->resolution_due_at,
                'resolution_time_hours'   => $ticket->sla->net_resolution_hours,
                'resolution_status'       => $ticket->sla->resolution_status,
            ] : null,
        ]);
    }

    /** Format satu pesan tiket */
    private function formatMessage(TicketMessage $message, Ticket $ticket, ?int $highlightMessageId = null): array
    {
        $replyToPreview = null;
        if ($message->reply_to_id && $message->replyTo) {
            $parent         = $message->replyTo;
            $replyToPreview = [
                'id'          => $parent->id,
                'sender_name' => $parent->sender_name,
                'text'        => mb_substr(strip_tags($parent->message_html ?: $parent->message ?? ''), 0, 120),
            ];
        }

        return [
            'id'                  => $message->id,
            'ticket_id'           => $message->ticket_id,
            'sender_type'         => $message->sender_type,
            'sender_id'           => $message->sender_id,
            'sender_name'         => $message->sender_name,
            'sender_email'        => $message->sender_email,
            'message_body'        => $message->message,
            'message_html'        => $message->message_html,
            'message_type'        => $message->message_type ?: ($message->is_internal_note ? 'internal_note' : 'reply'),
            'reply_to_id'         => $message->reply_to_id,
            'reply_to_preview'    => $replyToPreview,
            'channel'             => $message->channel ?? 'web',
            'is_read_by_customer' => $message->is_read_by_customer,
            'is_read_by_agent'    => $message->is_read_by_agent,
            'is_deleted'          => (bool) $message->is_deleted,
            'is_highlighted'      => $highlightMessageId !== null && $message->id === $highlightMessageId,
            'attachments'         => $message->attachments?->map(fn ($att) => [
                'id'        => $att->id,
                'file_name' => $att->file_name,
                'file_size' => $att->file_size,
                'mime_type' => $att->mime_type,
            ]) ?? [],
            'created_at' => $message->created_at,
        ];
    }
}
