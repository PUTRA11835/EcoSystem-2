<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminSessionController extends Controller
{
    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === 1;
    }

    public function index(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $lifetime = config('session.lifetime', 120);
            $expiredBefore = now()->subMinutes($lifetime)->timestamp;

            $sessions = DB::table('sessions')
                ->leftJoin('auth_users', 'sessions.user_id', '=', 'auth_users.id')
                ->leftJoin('employee', 'auth_users.employee_id', '=', 'employee.employee_id')
                ->leftJoin('employee_basic_data', 'employee.employee_id', '=', 'employee_basic_data.employee_id')
                ->where('sessions.last_activity', '>', $expiredBefore)
                ->select(
                    'sessions.id as session_id',
                    'sessions.user_id',
                    'sessions.ip_address',
                    'sessions.user_agent',
                    'sessions.last_activity',
                    'auth_users.username',
                    'auth_users.email',
                    'employee.eci',
                    DB::raw("CONCAT(COALESCE(employee_basic_data.first_name,''), ' ', COALESCE(employee_basic_data.last_name,'')) as full_name")
                )
                ->orderByDesc('sessions.last_activity')
                ->get()
                ->map(function ($s) {
                    $s->last_activity_at = date('Y-m-d H:i:s', $s->last_activity);
                    $s->is_current = ($s->session_id === session()->getId());
                    return $s;
                });

            return response()->json([
                'success' => true,
                'data'    => $sessions,
                'total'   => $sessions->count(),
            ]);
        } catch (\Exception $e) {
            Log::error('AdminSessionController@index error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to fetch sessions'], 500);
        }
    }

    public function destroy(Request $request, string $sessionId)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        if ($sessionId === session()->getId()) {
            return response()->json(['success' => false, 'message' => 'Cannot force-logout your own session'], 422);
        }

        try {
            $deleted = DB::table('sessions')->where('id', $sessionId)->delete();

            if (!$deleted) {
                return response()->json(['success' => false, 'message' => 'Session not found'], 404);
            }

            Log::info('AdminSessionController: force logout', [
                'target_session' => $sessionId,
                'by'             => session('user.eci') ?? session('user.name') ?? 'admin',
                'ip'             => $request->ip(),
            ]);

            return response()->json(['success' => true, 'message' => 'Session terminated']);
        } catch (\Exception $e) {
            Log::error('AdminSessionController@destroy error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to terminate session'], 500);
        }
    }

    public function destroyAll(Request $request)
    {
        if (!$this->assertAdmin()) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        try {
            $currentSessionId = session()->getId();
            $count = DB::table('sessions')
                ->where('id', '!=', $currentSessionId)
                ->whereNotNull('user_id')
                ->delete();

            Log::info('AdminSessionController: force logout ALL', [
                'count' => $count,
                'by'    => session('user.eci') ?? session('user.name') ?? 'admin',
                'ip'    => $request->ip(),
            ]);

            return response()->json([
                'success' => true,
                'message' => "Terminated {$count} session(s)",
                'count'   => $count,
            ]);
        } catch (\Exception $e) {
            Log::error('AdminSessionController@destroyAll error', [
                'error'    => $e->getMessage(),
                'error_at' => $e->getFile() . ':' . $e->getLine(),
            ]);
            return response()->json(['success' => false, 'message' => 'Failed to terminate sessions'], 500);
        }
    }

    public function page()
    {
        if (!$this->assertAdmin()) {
            abort(403);
        }
        return view('admin.sessions');
    }
}
