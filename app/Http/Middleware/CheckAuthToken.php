<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Log;

class CheckAuthToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip check untuk route tertentu
        if ($request->is('api/auth/login') || $request->is('api/auth/logout')) {
            return $next($request);
        }

        Log::info('CheckAuthToken middleware triggered', [
            'url' => $request->fullUrl(),
            'session_has_token' => session()->has('auth_token'),
            'session_has_user' => session()->has('user'),
            'session_id' => session()->getId()
        ]);

        // Cek token dari session
        if (!session()->has('auth_token')) {
            Log::warning('No auth token in session', [
                'url' => $request->fullUrl(),
                'session_id' => session()->getId()
            ]);

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }
            
            return redirect()->route('login')->with('error', 'Please login first');
        }

        Log::info('Auth token found, allowing request');

        return $next($request);
    }
}