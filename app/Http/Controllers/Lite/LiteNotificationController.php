<?php

namespace App\Http\Controllers\Lite;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LiteNotificationController extends Controller
{
    /**
     * GET /api/lite/notifications
     * 20 notifikasi terbaru + unread_count, untuk bell dropdown.
     * Adaptasi dari NotificationController::apiIndex().
     */
    public function index(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $employeeId = $user['id'];

        $notifications = DB::table('notifications as n')
            ->leftJoin('ticket as t', 't.ticket_id', '=', 'n.ticket_id')
            ->leftJoin('customer as c', 'c.customer_id', '=', 't.customer_id')
            ->where('n.employee_id', $employeeId)
            ->orderBy('n.created_at', 'desc')
            ->limit(20)
            ->select([
                'n.id', 'n.type', 'n.ticket_id', 'n.message_id',
                'n.from_name', 'n.preview', 'n.link', 'n.is_read', 'n.created_at',
                't.ticket_number',
                'c.customer_code',
            ])
            ->get()
            ->map(fn ($n) => [
                'id'            => $n->id,
                'type'          => $n->type,
                'ticket_id'     => $n->ticket_id,
                'ticket_number' => $n->ticket_number,
                'customer_name' => $n->customer_code,
                'message_id'    => $n->message_id,
                'from_name'     => $n->from_name,
                'preview'       => $n->preview,
                'link'          => $n->link,
                'is_read'       => (bool) $n->is_read,
                'created_at'    => $n->created_at ? \Carbon\Carbon::parse($n->created_at)->diffForHumans() : null,
            ]);

        $unreadCount = Notification::where('employee_id', $employeeId)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => $unreadCount,
        ]);
    }

    /**
     * GET /api/lite/notifications/unread-count
     * Endpoint ringan untuk polling badge.
     * Adaptasi dari NotificationController::unreadCount().
     */
    public function unreadCount(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'count' => 0], 401);
        }

        $employeeId   = $user['id'];
        $messageTypes = ['ticket_reply', 'ticket_internal_note', 'mention'];

        $count = Notification::where('employee_id', $employeeId)
            ->where('is_read', false)
            ->count();

        $messageCount = Notification::where('employee_id', $employeeId)
            ->whereIn('type', $messageTypes)
            ->where('is_read', false)
            ->count();

        return response()->json([
            'success'             => true,
            'count'               => $count,
            'message_sound_count' => $messageCount,
        ]);
    }

    /**
     * PUT /api/lite/notifications/{id}/read
     */
    public function markRead(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('employee_id', $user['id'])
            ->first();

        if (!$notification) {
            return response()->json(['success' => false, 'message' => 'Notification not found.'], 404);
        }

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * PUT /api/lite/notifications/read-all
     */
    public function markAllRead(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Notification::where('employee_id', $user['id'])
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/lite/notifications/{id}
     */
    public function deleteOne(Request $request, $id)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Notification::where('id', $id)
            ->where('employee_id', $user['id'])
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/lite/notifications/bulk-delete
     * Hapus semua notifikasi yang sudah dibaca milik employee ini.
     */
    public function bulkDelete(Request $request)
    {
        $user = $this->resolveUser($request);
        if (!$user) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Notification::where('employee_id', $user['id'])
            ->where('is_read', true)
            ->delete();

        return response()->json(['success' => true]);
    }

    private function resolveUser(Request $request): ?array
    {
        return $request->session()->get('user')
            ?? $request->attributes->get('lite_user');
    }
}
