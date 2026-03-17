<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckJarviesApiKey
{
    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-Api-Key') ?? $request->query('api_key');

        if (empty($apiKey) || $apiKey !== env('JARVIES_API_KEY')) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: invalid or missing API key',
            ], 401);
        }

        return $next($request);
    }
}
