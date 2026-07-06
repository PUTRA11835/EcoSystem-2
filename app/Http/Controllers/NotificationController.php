<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class NotificationController extends Controller
{
    /**
     * GET /notifications
     * Web page — list all notifications for the current employee.
     */
    public function index()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        $employeeId = $sessionUser['id'];

        $notifications = Notification::with([
                'ticket:ticket_id,ticket_number,customer_id',
                'ticket.customer:customer_id,customer_code',
            ])
            ->where('employee_id', $employeeId)
            ->orderBy('created_at', 'desc')
            ->paginate(30);

        return view('notifications.index', [
            'user'          => $sessionUser,
            'notifications' => $notifications,
        ]);
    }

    /**
     * GET /api/notifications
     * JSON — paginated list + unread count for the bell dropdown.
     */
    public function apiIndex(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $employeeId = $sessionUser['id'];

        // Bell dropdown menampilkan 20 notifikasi terbaru (read + unread), termasuk
        // ticket_reply / ticket_internal_note — badge memakai unread_count di bawah.
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
     * GET /api/notifications/unread-count
     * Lightweight endpoint for polling the bell badge.
     */
    public function unreadCount()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'count' => 0], 401);
        }

        $employeeId   = $sessionUser['id'];
        $messageTypes = ['ticket_reply', 'ticket_internal_note'];

        // Unified badge count — now includes chat/message types too, so ticket
        // replies show up in the bell like any other notification.
        $count = Notification::where('employee_id', $employeeId)
            ->where('is_read', false)
            ->count();

        // Message-type subset of the count above — the frontend uses this to
        // decide when to play the chat sound (vs the generic ticket sound).
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
     * PUT /api/notifications/{id}/read
     * Mark a single notification as read.
     */
    public function markRead($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notification = Notification::where('id', $id)
            ->where('employee_id', $sessionUser['id'])
            ->firstOrFail();

        $notification->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * PUT /api/notifications/read-all
     * Mark all notifications as read for the current employee.
     */
    public function markAllRead()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Notification::where('employee_id', $sessionUser['id'])
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/notifications/{id}
     * Delete a single notification.
     */
    public function deleteOne($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Notification::where('id', $id)
            ->where('employee_id', $sessionUser['id'])
            ->delete();

        return response()->json(['success' => true]);
    }

    /**
     * DELETE /api/notifications/bulk-delete
     * Delete all read notifications for the current employee.
     */
    public function bulkDelete()
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        Notification::where('employee_id', $sessionUser['id'])
            ->where('is_read', true)
            ->delete();

        return response()->json(['success' => true]);
    }
}
