<?php

namespace App\Http\Middleware;

use App\Models\AuthUser;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureMobileEmployee
{
    /**
     * Middleware untuk endpoint mobile yang hanya boleh diakses oleh employee.
     *
     * Validasi:
     * 1. Bearer token ada di header Authorization
     * 2. Token valid dan belum expired (harus access token, bukan refresh token)
     * 3. Pemilik token adalah employee (employee_id tidak null)
     * 4. Akun aktif (is_active = true)
     */
    public function handle(Request $request, Closure $next): Response
    {
        $bearerToken = $request->bearerToken();

        if (!$bearerToken) {
            return response()->json([
                'success' => false,
                'message' => 'No authentication token provided. Please log in to continue.',
                'code'    => 'UNAUTHENTICATED',
            ], 401);
        }

        // Format Sanctum token: "{id}|{plaintext}"
        $parts = explode('|', $bearerToken, 2);

        if (count($parts) !== 2) {
            return response()->json([
                'success' => false,
                'message' => 'The provided authentication token has an invalid format.',
                'code'    => 'INVALID_TOKEN',
            ], 401);
        }

        [$tokenId, $rawToken] = $parts;

        $tokenRecord = DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->first();

        // Verifikasi hash
        if (!$tokenRecord || !hash_equals($tokenRecord->token, hash('sha256', $rawToken))) {
            return response()->json([
                'success' => false,
                'message' => 'The authentication token is invalid or has been revoked.',
                'code'    => 'INVALID_TOKEN',
            ], 401);
        }

        // Tolak jika ini adalah refresh token (bukan access token)
        $abilities = json_decode($tokenRecord->abilities, true) ?? [];
        if (in_array('refresh', $abilities) && !in_array('*', $abilities)) {
            return response()->json([
                'success' => false,
                'message' => 'A refresh token cannot be used to access this endpoint. Please use an access token.',
                'code'    => 'INVALID_TOKEN_TYPE',
            ], 401);
        }

        // Cek apakah access token sudah expired
        if ($tokenRecord->expires_at && now()->isAfter($tokenRecord->expires_at)) {
            return response()->json([
                'success' => false,
                'message' => 'Your access token has expired. Please use your refresh token to obtain a new one.',
                'code'    => 'ACCESS_TOKEN_EXPIRED',
            ], 401);
        }

        // Load user
        $authUser = AuthUser::find($tokenRecord->tokenable_id);

        if (!$authUser || !$authUser->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your account is inactive or does not exist. Please contact your administrator.',
                'code'    => 'ACCOUNT_INACTIVE',
            ], 403);
        }

        // Pastikan hanya employee
        if (!$authUser->isEmployee()) {
            return response()->json([
                'success' => false,
                'message' => 'Access denied. This endpoint is restricted to employees only.',
                'code'    => 'NOT_EMPLOYEE',
            ], 403);
        }

        // Update last_used_at
        DB::table('personal_access_tokens')
            ->where('id', $tokenId)
            ->update(['last_used_at' => now()]);

        // Set authenticated user agar $request->user() bisa dipakai di controller
        $request->setUserResolver(fn () => $authUser);

        return $next($request);
    }
}
