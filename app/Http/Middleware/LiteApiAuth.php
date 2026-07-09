<?php

namespace App\Http\Middleware;

use App\Http\Controllers\AuthController;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class LiteApiAuth
{
    /**
     * Validasi request untuk Lite API.
     *
     * Mendukung dua jalur autentikasi:
     * 1. Session cookie (web app — browser kirim cookie otomatis)
     * 2. Bearer token dari header Authorization (SPA / mobile web tanpa cookie)
     *
     * Token format: base64(ECI|timestamp|employee)
     * Expiry: 24 jam dari timestamp dalam token.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── Jalur 1: Session masih valid ────────────────────────────────────
        if (session()->has('auth_token') && session()->has('user')) {
            return $next($request);
        }

        // ── Jalur 2: Bearer token ────────────────────────────────────────────
        $bearerToken = $request->bearerToken();

        if ($bearerToken) {
            $user = $this->resolveUserFromToken($bearerToken);
            if ($user) {
                $request->attributes->set('lite_user', $user);
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'message' => 'Unauthorized. Please login first.',
        ], 401);
    }

    /**
     * Decode Bearer token → ambil ECI → query employee dari DB.
     * Return null jika token tidak valid, expired, atau employee tidak aktif.
     */
    private function resolveUserFromToken(string $rawToken): ?array
    {
        try {
            $decoded = base64_decode($rawToken, strict: true);
            if ($decoded === false) {
                return null;
            }

            $parts = explode('|', $decoded);
            if (count($parts) < 3) {
                return null;
            }

            [$eci, $timestamp, $type] = $parts;

            // Token hanya berlaku 24 jam
            if (!is_numeric($timestamp) || (time() - (int) $timestamp) > 86400) {
                return null;
            }

            if ($type !== 'employee') {
                return null;
            }

            // Cari employee berdasarkan ECI
            $employee = DB::table('employee')
                ->where('eci', $eci)
                ->where('is_active', true)
                ->first();

            if (!$employee) {
                return null;
            }

            // Pastikan punya akses sistem
            $hasAccess = DB::table('employee_role_assignment as era')
                ->join('employee_role as er', 'era.role_id', '=', 'er.id')
                ->where('era.employee_id', $employee->employee_id)
                ->where('er.name', 'User System Registered')
                ->exists();

            if (!$hasAccess) {
                return null;
            }

            // Bangun user data pakai helper yang sama dengan login
            $sessionData = AuthController::buildEmployeeSessionData($employee->employee_id);

            return $sessionData ? $sessionData['userData'] : null;

        } catch (\Throwable $e) {
            Log::warning('LiteApiAuth: token resolution failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}
