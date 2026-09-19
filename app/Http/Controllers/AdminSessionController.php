<?php

namespace App\Http\Controllers;

use App\Enums\RoleId;
use App\Services\IpLocationService;
use App\Support\SessionPayloadDecoder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AdminSessionController extends Controller
{
    /**
     * ECI of the protected super-admin account - never force-logoutable,
     * individually or via "logout all others".
     */
    private const PROTECTED_ECI = 'ECI_ADMIN';

    private function assertAdmin(): bool
    {
        return (int) session('user.role.id') === RoleId::EC_ADMINISTRATOR->value;
    }

    private function decodeSessionUser(?string $payload): ?array
    {
        return SessionPayloadDecoder::decode($payload);
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
                ->where('last_activity', '>', $expiredBefore)
                ->select('id as session_id', 'ip_address', 'user_agent', 'last_activity', 'payload')
                ->orderByDesc('last_activity')
                ->get()
                ->map(function ($s) {
                    $user = $this->decodeSessionUser($s->payload);

                    $s->user_id       = $user['id'] ?? null;
                    $s->username      = null;
                    $s->email         = $user['email'] ?? null;
                    $s->eci           = $user['eci'] ?? null;
                    $s->full_name     = $user['name'] ?? null;
                    $s->last_activity_at = date('Y-m-d H:i:s', $s->last_activity);
                    $s->is_current    = ($s->session_id === session()->getId());
                    $s->is_protected  = ($s->eci === self::PROTECTED_ECI);

                    unset($s->payload);
                    return $s;
                })
                // Drop sessions that never completed a login (failed attempts,
                // or just someone sitting on the login page) - only rows with
                // a decoded `user` payload represent an actual signed-in user.
                ->filter(fn ($s) => $s->user_id !== null)
                ->values();

            // Location, not just a raw IP: resolve once per DISTINCT ip_address
            // (cached 24h per IP in IpLocationService) rather than once per
            // session row, since the same employee/office IP often shows up
            // across several concurrent sessions.
            $locations = IpLocationService::formatMany($sessions->pluck('ip_address')->all());
            $sessions  = $sessions->map(function ($s) use ($locations) {
                $s->location = $locations[$s->ip_address] ?? 'Unknown location';
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

        $payload = DB::table('sessions')->where('id', $sessionId)->value('payload');
        $user    = $this->decodeSessionUser($payload);

        if (($user['eci'] ?? null) === self::PROTECTED_ECI) {
            return response()->json(['success' => false, 'message' => 'This account is protected and cannot be force-logged-out'], 422);
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

            $candidates = DB::table('sessions')
                ->where('id', '!=', $currentSessionId)
                ->select('id', 'payload')
                ->get();

            $deletableIds = $candidates
                ->filter(function ($s) {
                    $user = $this->decodeSessionUser($s->payload);

                    // Only drop identified (logged-in) sessions, and never the protected admin.
                    return $user !== null && ($user['eci'] ?? null) !== self::PROTECTED_ECI;
                })
                ->pluck('id');

            $count = DB::table('sessions')->whereIn('id', $deletableIds)->delete();

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
