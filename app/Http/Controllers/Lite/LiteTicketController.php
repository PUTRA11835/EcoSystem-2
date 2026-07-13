<?php

namespace App\Http\Controllers\Lite;

use App\Enums\RoleId;
use App\Http\Controllers\Controller;
use App\Http\Controllers\TicketMessageController;
use App\Models\EmployeeBasicData;
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

            $query = Ticket::with(['customer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy', 'deliverySupportActivities.deliverySupport.client.basicData'])
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
                'deliverySupportActivities.deliverySupport.client.basicData',
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

            // Resolve nama employee yang di-mention sekali untuk semua pesan (hindari N+1).
            $allMentionedIds = $messages->flatMap(fn ($msg) => $msg->mentioned_employee_ids ?? [])->unique()->values()->all();
            $employeeNamesById = $this->resolveEmployeeNames($allMentionedIds);

            $formatted = $messages->map(fn ($msg) => $this->formatMessage($msg, $ticket, $highlightMessageId, $employeeNamesById));

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
     * Reply dikirim email-first ke customer (via M365 Graph, sama seperti web) —
     * lihat TicketMessageController::sendLiteReply(). Internal note tidak pernah
     * dikirim ke email. Lite API belum mendukung lampiran untuk chat.
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
                'message'                  => 'required|string|min:1',
                'message_type'             => 'nullable|in:reply,internal_note',
                'is_internal_note'         => 'nullable|boolean',
                'reply_to_id'              => 'nullable|integer|exists:ticket_message,id',
                'ticket_status'            => 'nullable|in:inprocess,waiting_on_customer,waiting_to_confirmation,waiting_on_3rd_party,hold',
                // to_emails/cc_emails: hanya dipakai untuk message_type=reply. Boleh array
                // objek {address,name}, array string, atau JSON string dari objek tsb.
                'to_emails'                => 'nullable',
                'cc_emails'                => 'nullable',
                // mentioned_*: hanya dipakai untuk internal note (mention di reply customer belum didukung).
                'mentioned_employee_ids'   => 'nullable|array',
                'mentioned_employee_ids.*' => 'integer',
                'mentioned_role_ids'       => 'nullable|array',
                'mentioned_role_ids.*'     => 'integer',
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

            $emailFailed = false;

            if ($isInternalNote) {
                $mentionedEmployeeIds = $request->input('mentioned_employee_ids', []);
                $mentionedRoleIds     = $request->input('mentioned_role_ids', []);

                $message = TicketMessage::create([
                    'ticket_id'              => $ticketId,
                    'sender_type'            => 'employee',
                    'sender_id'              => $user['id'],
                    'sender_name'            => $user['name'],
                    'sender_email'           => $user['email'] ?? null,
                    'message'                => strip_tags($request->message),
                    'message_html'           => $request->message,
                    'message_type'           => 'internal_note',
                    'is_internal_note'       => true,
                    'reply_to_id'            => $request->reply_to_id,
                    'channel'                => 'web',
                    'is_read_by_customer'    => false,
                    'is_read_by_agent'       => true,
                    'mentioned_employee_ids' => !empty($mentionedEmployeeIds) ? $mentionedEmployeeIds : null,
                    'mentioned_role_ids'     => !empty($mentionedRoleIds) ? $mentionedRoleIds : null,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]);

                $ticket->update([
                    'last_message_at'              => now(),
                    'last_internal_note_at'        => now(),
                    'last_internal_note_sender_id' => $user['id'],
                ]);

                // Notifikasi mention (non-fatal) — reuse logic yang sama dengan web.
                if (!empty($mentionedEmployeeIds) || !empty($mentionedRoleIds)) {
                    app(TicketMessageController::class)->createMentionNotifications(
                        $message,
                        $ticket,
                        (int) $user['id'],
                        $user['name'],
                        $mentionedEmployeeIds,
                        $mentionedRoleIds
                    );
                }
            } else {
                // Parse CC — array, atau JSON string berisi array. null = tidak dikirim
                // (pakai CC tiket saat ini); [] eksplisit = hapus semua CC.
                $requestCcRaw = $request->input('cc_emails');
                $requestCc    = null;
                if ($requestCcRaw !== null) {
                    $requestCc = is_array($requestCcRaw) ? $requestCcRaw : (json_decode($requestCcRaw, true) ?? []);
                }

                // Parse TO — item pertama jadi primary recipient, sisanya additional.
                // null = tidak dikirim (fallback ke resolveCustomerEmail/legacy).
                $requestToRaw = $request->input('to_emails');
                $requestTo    = null;
                if ($requestToRaw !== null) {
                    $decoded   = is_array($requestToRaw) ? $requestToRaw : (json_decode($requestToRaw, true) ?? []);
                    $requestTo = array_values(array_filter(array_map(
                        fn ($e) => is_string($e) ? trim($e) : (is_array($e) ? trim((string) ($e['address'] ?? '')) : ''),
                        $decoded
                    )));
                }

                // Reply — email-first ke customer (To/CC dari request bila dikirim,
                // fallback ke To/CC tiket), fallback tersimpan internal bila email
                // gagal/tidak ada penerima.
                $result = app(TicketMessageController::class)->sendLiteReply(
                    $ticket,
                    [
                        'id'        => (int) $user['id'],
                        'name'      => $user['name'],
                        'nick_name' => $user['nick_name'] ?? null,
                    ],
                    $request->message,
                    $request->input('ticket_status'),
                    $requestCc,
                    $requestTo
                );

                $message     = $result['message'];
                $emailFailed = $result['email_failed'];

                if ($request->reply_to_id) {
                    $message->update(['reply_to_id' => $request->reply_to_id]);
                }
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Message sent successfully.',
                'email_failed' => $emailFailed,
                'email_status' => $message->email_status,
                'email_error'  => $message->email_error,
                'data'         => $this->formatMessage($message->fresh(['attachments', 'replyTo']), $ticket),
            ], 201);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@addMessage error', ['error' => $e->getMessage(), 'ticket_id' => $ticketId]);
            return response()->json(['success' => false, 'message' => 'Failed to send message.'], 500);
        }
    }

    /**
     * Edit internal note milik sendiri (dalam 10 menit sejak dibuat) — Lite API.
     * Aturan identik dengan TicketMessageController::updateInternalNote() (web):
     * hanya sender sendiri, dalam window 10 menit, dan note belum dihapus.
     *
     * POST /api/lite/tickets/{ticketId}/messages/{messageId}/internal-note
     */
    public function updateInternalNote(Request $request, int $ticketId, int $messageId)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $message = TicketMessage::where('ticket_id', $ticketId)
                ->where('id', $messageId)
                ->where('is_internal_note', true)
                ->first();

            if (!$message) {
                return response()->json(['success' => false, 'message' => 'Note not found.'], 404);
            }

            if ((int) $message->sender_id !== (int) ($user['id'] ?? 0)) {
                return response()->json(['success' => false, 'message' => 'You can only edit your own notes.'], 403);
            }

            if ($message->created_at->addMinutes(10)->isPast()) {
                return response()->json(['success' => false, 'message' => 'Notes can only be edited within 10 minutes of posting.'], 403);
            }

            if ($message->is_deleted) {
                return response()->json(['success' => false, 'message' => 'Cannot edit a deleted note.'], 422);
            }

            $validator = Validator::make($request->all(), [
                'message' => 'required|string|min:1',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => $validator->errors()->first(),
                    'errors'  => $validator->errors(),
                ], 422);
            }

            $message->update([
                'message'      => strip_tags($request->message),
                'message_html' => $request->message,
                'edited_at'    => now(),
            ]);

            $ticket = Ticket::find($ticketId);

            return response()->json([
                'success' => true,
                'message' => 'Note updated.',
                'data'    => $this->formatMessage($message->fresh(['attachments', 'replyTo']), $ticket),
            ]);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@updateInternalNote error', [
                'error'      => $e->getMessage(),
                'ticket_id'  => $ticketId,
                'message_id' => $messageId,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to update note.'], 500);
        }
    }

    /**
     * Soft-delete (unsend) internal note — Lite API.
     * Aturan identik dengan TicketMessageController::destroyInternalNote() (web):
     * sender sendiri dalam window 10 menit, atau admin (EC_ADMINISTRATOR) kapan saja.
     *
     * DELETE /api/lite/tickets/{ticketId}/messages/{messageId}/internal-note
     */
    public function destroyInternalNote(Request $request, int $ticketId, int $messageId)
    {
        try {
            $user = $this->resolveUser($request);
            if (!$user) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $message = TicketMessage::where('ticket_id', $ticketId)
                ->where('id', $messageId)
                ->where('is_internal_note', true)
                ->first();

            if (!$message) {
                return response()->json(['success' => false, 'message' => 'Note not found.'], 404);
            }

            $roleIds = !empty($user['role_ids'])
                ? array_map('intval', $user['role_ids'])
                : DB::table('employee_role_assignment')
                    ->where('employee_id', (int) ($user['id'] ?? 0))
                    ->pluck('role_id')->map(fn ($id) => (int) $id)->toArray();

            $isAdmin  = in_array(RoleId::EC_ADMINISTRATOR->value, $roleIds, true);
            $isSender = $message->sender_id !== null
                && (int) $message->sender_id === (int) ($user['id'] ?? 0);

            if (!$isAdmin && !$isSender) {
                return response()->json(['success' => false, 'message' => 'You can only delete your own notes.'], 403);
            }

            if (!$isAdmin && $message->created_at->addMinutes(10)->isPast()) {
                return response()->json(['success' => false, 'message' => 'Notes can only be deleted within 10 minutes of posting.'], 403);
            }

            if ($message->is_deleted) {
                return response()->json(['success' => false, 'message' => 'Note already deleted.'], 422);
            }

            $message->update(['is_deleted' => true]);

            return response()->json(['success' => true, 'message' => 'Note deleted.']);

        } catch (\Exception $e) {
            Log::error('LiteTicketController@destroyInternalNote error', [
                'error'      => $e->getMessage(),
                'ticket_id'  => $ticketId,
                'message_id' => $messageId,
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to delete note.'], 500);
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

            $query = Ticket::with(['customer.basicData', 'ticketLead.basicData', 'members.basicData', 'sla.policy', 'deliverySupportActivities.deliverySupport.client.basicData'])
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
            'delivery' => $this->formatDelivery($ticket),
        ];
    }

    /** Delivery support tempat tiket ini ditangani (via delivery_support_activities) */
    private function formatDelivery(Ticket $ticket): ?array
    {
        $deliverySupport = $ticket->deliverySupportActivities->first()?->deliverySupport;
        if (!$deliverySupport) {
            return null;
        }

        $clientName = $deliverySupport->client?->basicData?->name_1;

        return [
            'delivery_id'    => $deliverySupport->id,
            'delivery_name'  => $deliverySupport->name,
            'delivery_type'  => $deliverySupport->type,
            'client_name'    => $clientName,
            // Format sama dengan dropdown "Assign to Delivery Support" di web: "{name} ({client}), {type}"
            'delivery_label' => trim($deliverySupport->name
                . ($clientName ? " ({$clientName})" : '')
                . ($deliverySupport->type ? ", {$deliverySupport->type}" : '')),
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
            // Untuk pre-fill composer reply di mobile — nilai To/CC tersimpan
            // terakhir (hasil reply sebelumnya dari web/mobile). Kirim balik field
            // yang sama saat POST .../messages untuk mempertahankannya, atau kirim
            // daftar baru untuk override (lihat POST /tickets/{ticketId}/messages).
            'to_emails'                => $ticket->to_emails ?? [],
            'cc_emails'                => $ticket->cc_emails ?? [],
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

    /** Resolve {employee_id: name} untuk sekumpulan id — dipakai untuk field `mentions` */
    private function resolveEmployeeNames(array $employeeIds): array
    {
        if (empty($employeeIds)) {
            return [];
        }

        return EmployeeBasicData::whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'first_name', 'last_name'])
            ->mapWithKeys(fn ($bd) => [(int) $bd->employee_id => $bd->full_name])
            ->all();
    }

    /** Format satu pesan tiket */
    private function formatMessage(TicketMessage $message, Ticket $ticket, ?int $highlightMessageId = null, ?array $employeeNamesById = null): array
    {
        $mentionedEmployeeIds = $message->mentioned_employee_ids ?? [];
        if ($employeeNamesById === null && !empty($mentionedEmployeeIds)) {
            $employeeNamesById = $this->resolveEmployeeNames($mentionedEmployeeIds);
        }
        $mentions = collect($mentionedEmployeeIds)->map(fn ($id) => [
            'employee_id' => (int) $id,
            'name'        => $employeeNamesById[(int) $id] ?? null,
        ])->values()->all();

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
            'email_status'        => $message->email_status,
            'email_error'         => $message->email_error,
            'is_read_by_customer' => $message->is_read_by_customer,
            'is_read_by_agent'    => $message->is_read_by_agent,
            'is_deleted'          => (bool) $message->is_deleted,
            'edited_at'           => $message->edited_at?->toIso8601String(),
            'is_highlighted'      => $highlightMessageId !== null && $message->id === $highlightMessageId,
            'mentions'            => $mentions,
            'mentioned_role_ids'  => $message->mentioned_role_ids ?? [],
            'attachments'         => $message->attachments?->map(fn ($att) => [
                'id'        => $att->id,
                'file_name' => $att->file_name,
                'file_size' => $att->file_size,
                'mime_type' => $att->mime_type,
                // Selalu lewat proxy Lite API (Bearer token), bukan public_url (butuh session web).
                'url'       => route('lite.attachments.show', $att->id),
                'is_image'  => $att->is_image,
            ]) ?? [],
            'created_at' => $message->created_at,
        ];
    }
}
