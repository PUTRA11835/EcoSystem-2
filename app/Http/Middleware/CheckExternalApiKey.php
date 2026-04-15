<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckExternalApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key') ?? $request->query('api_key');

        $expected = config('services.external_ticket.api_key');

        if (empty($apiKey) || empty($expected) || !hash_equals($expected, $apiKey)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: invalid or missing API key',
            ], 401);
        }

        return $next($request);
    }
}
