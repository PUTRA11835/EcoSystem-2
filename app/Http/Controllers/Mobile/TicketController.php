<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Http\Resources\Mobile\TicketDetailResource;
use App\Http\Resources\Mobile\TicketListResource;
use App\Http\Resources\Mobile\TicketMessageResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketMessage;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

/**
 * Mobile Ticket Controller
 *
 * All endpoints are protected by the `mobile.employee` middleware
 * (Sanctum Bearer token, employees only).
 *
 * Authenticated user: $request->user() → AuthUser instance
 * AuthUser->employee_id → ID of the currently logged-in employee.
 */
class TicketController extends Controller
{
    // =========================================================================
    // GET /api/mobile/employee/tickets
    // =========================================================================

    public function index(Request $request)
    {
        try {
            $stats = DB::table('ticket')
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'hold'        THEN 1 ELSE 0 END) as hold,
                    SUM(CASE WHEN status = 'closed'      THEN 1 ELSE 0 END) as closed
                ")
                ->first();

            $query = Ticket::with(['customer.basicData', 'employee.basicData'])
                ->whereNull('deleted_at');

            if ($request->boolean('assigned_to_me')) {
                $query->where('employee_id', $request->user()->employee_id);
            }

            if ($search = $request->query('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('subject', 'like', "%{$search}%")
                      ->orWhere('description', 'like', "%{$search}%")
                      ->orWhereHas('customer.basicData', fn($cq) =>
                          $cq->where('name_1', 'like', "%{$search}%")
                      );
                });
            }

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
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@index', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket list. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/mobile/employee/tickets/{id}
    // =========================================================================

    public function show($id)
    {
        try {
            $ticket = Ticket::with([
                'customer.basicData',
                'employee.basicData',
                'members.basicData',
            ])->where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();

            return response()->json([
                'success' => true,
                'data'    => new TicketDetailResource($ticket),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@show', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket details. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets
    // =========================================================================

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
                'message' => 'Ticket creation data is invalid.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
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
                'message' => 'Ticket created successfully.',
                'data'    => new TicketDetailResource($ticket),
            ], 201);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@store', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to create the ticket. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/mobile/employee/tickets/{id}/messages
    // =========================================================================

    public function getMessages($id)
    {
        try {
            $ticket = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();

            $messages = TicketMessage::where('ticket_id', $ticket->ticket_id)
                ->where('is_internal_note', false)
                ->orderBy('created_at', 'asc')
                ->get();

            return response()->json([
                'success' => true,
                'data'    => TicketMessageResource::collection($messages),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@getMessages', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket messages. Please try again.',
            ], 500);
        }
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
                'message' => 'Message content is required.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $ticket   = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
            $authUser = $request->user();

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
                'last_message_at'     => now(),
                'last_agent_reply_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully.',
                'data'    => new TicketMessageResource($message),
            ], 201);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@sendMessage', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send the message. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets/{id}/ownership
    // =========================================================================

    public function takeOwnership(Request $request, $id)
    {
        try {
            $ticket   = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
            $authUser = $request->user();

            $ticket->update(['employee_id' => $authUser->employee_id]);
            $ticket->load(['customer.basicData', 'employee.basicData', 'members.basicData']);

            return response()->json([
                'success' => true,
                'message' => 'Ticket ownership transferred successfully.',
                'data'    => new TicketDetailResource($ticket),
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@takeOwnership', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer ticket ownership. Please try again.',
            ], 500);
        }
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
                'message' => 'man_days is required and must be a non-negative number.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $ticket = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
            $ticket->update(['man_days' => $request->man_days]);

            return response()->json([
                'success' => true,
                'message' => 'Mandays updated successfully.',
                'data'    => ['man_days' => (float) $ticket->man_days],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@updateMandays', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update mandays. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // PUT /api/mobile/employee/tickets/{id}/status
    // =========================================================================

    public function updateStatus(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'status' => 'required|string|in:Open,In Progress,Hold,Reply,Closed',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Ticket status is invalid. Allowed values: Open, In Progress, Hold, Reply, Closed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $ticket   = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();
            $dbStatus = $this->mapStatusToDb($request->status);

            $ticket->update(['status' => $dbStatus]);

            return response()->json([
                'success' => true,
                'message' => 'Ticket status updated successfully.',
                'data'    => [
                    'id'     => $ticket->ticket_id,
                    'status' => $ticket->fresh()->status_label,
                ],
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@updateStatus', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to update ticket status. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // GET /api/mobile/employee/tickets/stats
    // =========================================================================

    public function stats()
    {
        try {
            $row = DB::table('ticket')
                ->whereNull('deleted_at')
                ->selectRaw("
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'open'        THEN 1 ELSE 0 END) as open,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                    SUM(CASE WHEN status = 'hold'        THEN 1 ELSE 0 END) as hold,
                    SUM(CASE WHEN status = 'reply'       THEN 1 ELSE 0 END) as reply,
                    SUM(CASE WHEN status = 'closed'      THEN 1 ELSE 0 END) as closed
                ")
                ->first();

            return response()->json([
                'success' => true,
                'data'    => [
                    'total'       => (int) $row->total,
                    'open'        => (int) $row->open,
                    'in_progress' => (int) $row->in_progress,
                    'hold'        => (int) $row->hold,
                    'reply'       => (int) $row->reply,
                    'closed'      => (int) $row->closed,
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@stats', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve ticket statistics. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // POST /api/mobile/employee/tickets/{id}/send-to-customer
    // =========================================================================

    public function sendToCustomer(Request $request, $id)
    {
        try {
            $ticket = Ticket::where('ticket_id', $id)->whereNull('deleted_at')->firstOrFail();

            $ticket->update([
                'status'              => 'reply',
                'last_agent_reply_at' => now(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Customer notification sent successfully.',
            ]);
        } catch (ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => "Ticket #{$id} not found or has been deleted.",
            ], 404);
        } catch (\Exception $e) {
            Log::error('Mobile\TicketController@sendToCustomer', ['id' => $id, 'error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Failed to send notification to customer. Please try again.',
            ], 500);
        }
    }

    // =========================================================================
    // HELPERS
    // =========================================================================

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
