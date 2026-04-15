<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckJarviesApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey   = $request->header('X-Api-Key') ?? $request->query('api_key');
        $expected = config('services.jarvies.api_key');

        // hash_equals mencegah timing attack; env() diganti config() agar berfungsi saat config:cache
        if (empty($apiKey) || empty($expected) || !hash_equals($expected, $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: invalid or missing API key',
            ], 401);
        }

        return $next($request);
    }
}
