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
     *
     * Mendukung `?tab=unread|read|all` (bawaan: all), `?q=` pencarian teks
     * bebas pada kolom `preview`, dan `?type=pending_approval` — ditambahkan
     * Keputusan HC-D39/HC-D40 untuk menyamai pola "Pusat Notifikasi" ESH
     * (kartu ringkasan + tab + cari) DAN menjadi tujuan klik langsung dari
     * kotak "N Pending Approval" di Command Center (Dashboard): filter
     * `type=pending_approval` mempersempit daftar ke 5 tipe `*_pending_approval`
     * — dokumen yang BENAR-BENAR menunggu tindakan orang ini, bukan seluruh
     * riwayat notifikasi. Hitungan Unread/Total pada kartu ringkasan dihitung
     * dari SELURUH baris milik karyawan ini (dalam cakupan filter yang sama),
     * bukan hanya halaman yang sedang tampil.
     */
    public function index(Request $request)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return redirect()->route('login');
        }

        $employeeId     = $sessionUser['id'];
        $tab            = in_array($request->query('tab'), ['unread', 'read'], true) ? $request->query('tab') : 'all';
        $search         = trim((string) $request->query('q', ''));
        $pendingOnly    = $request->query('type') === 'pending_approval';

        $base = Notification::where('employee_id', $employeeId)
            ->when($pendingOnly, fn ($q) => $q->where('type', 'like', '%_pending_approval'));

        $unreadCount = (clone $base)->where('is_read', false)->count();
        $totalCount  = (clone $base)->count();

        $query = Notification::with([
                'ticket:ticket_id,ticket_number,customer_id',
                'ticket.customer:customer_id,customer_code',
            ])
            ->where('employee_id', $employeeId)
            ->when($pendingOnly, fn ($q) => $q->where('type', 'like', '%_pending_approval'));

        if ($tab === 'unread') {
            $query->where('is_read', false);
        } elseif ($tab === 'read') {
            $query->where('is_read', true);
        }

        if ($search !== '') {
            $query->where('preview', 'like', '%' . $search . '%');
        }

        $notifications = $query->orderBy('created_at', 'desc')
            ->paginate(30)
            ->withQueryString();

        return view('notifications.index', [
            'user'          => $sessionUser,
            'notifications' => $notifications,
            'tab'           => $tab,
            'search'        => $search,
            'pendingOnly'   => $pendingOnly,
            'unreadCount'   => $unreadCount,
            'totalCount'    => $totalCount,
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

        // Bell dropdown menampilkan 20 notifikasi UNREAD terbaru saja — begitu dibaca
        // (satuan atau lewat batch per-ticket di markRead()), notifikasi itu hilang dari
        // dropdown. Riwayat lengkap (read + unread) tetap ada di halaman /notifications.
        $notifications = DB::table('notifications as n')
            ->leftJoin('ticket as t', 't.ticket_id', '=', 'n.ticket_id')
            ->leftJoin('customer as c', 'c.customer_id', '=', 't.customer_id')
            ->where('n.employee_id', $employeeId)
            ->where('n.is_read', false)
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
     * Mark a notification as read. If it's tied to a ticket, every other unread
     * notification this employee has for that same ticket is marked read too —
     * clicking into a ticket means you've seen everything pending on it, not just
     * the one item you happened to click.
     */
    public function markRead($id)
    {
        $sessionUser = session('user');
        if (!$sessionUser) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $employeeId   = $sessionUser['id'];
        $notification = Notification::where('id', $id)
            ->where('employee_id', $employeeId)
            ->firstOrFail();

        $now = now();
        $notification->update(['is_read' => true, 'read_at' => $now]);

        if ($notification->ticket_id) {
            Notification::where('employee_id', $employeeId)
                ->where('ticket_id', $notification->ticket_id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => $now]);
        }

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
