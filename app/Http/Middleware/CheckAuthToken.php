<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class CheckAuthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // ── 1. Session masih valid (jalur normal) ────────────────────────────
        if (session()->has('auth_token')) {
            $this->hydratePreferences($request);
            return $next($request);
        }

        // ── 2. Session expired — coba restore via remember-me cookie ─────────
        $rememberCookie = $request->cookie(AuthController::REMEMBER_COOKIE);

        if ($rememberCookie) {
            $restored = $this->tryRestoreSession($request, $rememberCookie);
            if ($restored) {
                return $next($request);
            }

            // Cookie ada tapi tidak valid — bersihkan
            $expired = cookie(AuthController::REMEMBER_COOKIE, '', -1);
        }

        // ── 3. Tidak ada session dan tidak ada cookie valid ───────────────────
        if ($request->expectsJson() || $request->is('api/*')) {
            $response = response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 401);

            if (isset($expired)) {
                $response->withCookie($expired);
            }

            return $response;
        }

        $redirect = redirect()->route('login')->with('error', 'Please login first');

        if (isset($expired)) {
            $redirect->withCookie($expired);
        }

        return $redirect;
    }

    /**
     * Muat preferensi user dari DB ke session sekali per session (bila belum ada).
     * Ini menjaga tema/warna/dll tetap konsisten setelah login baru atau restore
     * remember-me — bukan hanya setelah membuka halaman Settings.
     *
     * Dibungkus try/catch: kegagalan load preferensi TIDAK boleh men-crash request;
     * cukup lewati dan biarkan view memakai default-nya.
     */
    private function hydratePreferences(Request $request): void
    {
        if (session()->has('user_preferences')) {
            return;
        }

        try {
            $user = session('user');
            if (!$user || empty($user['id'])) {
                return;
            }

            $column = ($user['type'] ?? 'employee') === 'customer' ? 'customer_id' : 'employee_id';

            $raw = DB::table('auth_users')->where($column, $user['id'])->value('preferences');
            if (!$raw) {
                return;
            }

            $prefs = is_array($raw) ? $raw : json_decode($raw, true);
            if (is_array($prefs) && $prefs) {
                session(['user_preferences' => $prefs]);
            }
        } catch (Throwable $e) {
            Log::warning('CheckAuthToken: hydrate preferences gagal', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Coba restore session dari remember-me cookie.
     * Return true jika berhasil, false jika token tidak valid / user tidak aktif.
     *
     * PENTING: seluruh proses dibungkus try/catch. Kegagalan restore (mis. query
     * error karena skema DB berbeda) TIDAK boleh melempar 500 — cukup anggap
     * cookie tidak valid dan jatuh ke jalur "silakan login".
     */
    private function tryRestoreSession(Request $request, string $rawToken): bool
    {
        try {
            // Hash cookie value dan cari di DB
            $hashed  = hash('sha256', $rawToken);
            $authUser = DB::table('auth_users')
                ->where('remember_token', $hashed)
                ->where('is_active', true)
                ->first();

            if (!$authUser || empty($authUser->employee_id)) {
                return false;
            }

            // Bangun ulang session data employee
            $sessionData = AuthController::buildEmployeeSessionData($authUser->employee_id);

            if (!$sessionData) {
                return false;
            }

            // Update last_login_at
            DB::table('auth_users')
                ->where('id', $authUser->id)
                ->update(['last_login_at' => now()]);

            // Regenerate session (keamanan) & isi ulang
            $request->session()->regenerate();
            $request->session()->put('auth_token', $sessionData['token']);
            $request->session()->put('user', $sessionData['userData']);
            $request->session()->save();

            return true;
        } catch (Throwable $e) {
            // Restore gagal — jangan sampai men-crash request. Log lalu perlakukan
            // sebagai cookie tidak valid.
            Log::warning('CheckAuthToken: remember-me restore gagal', [
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
}
