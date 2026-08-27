<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gzip-encodes JSON API responses when the client advertises gzip support.
 * zlib.output_compression is off at the php.ini level and the web server
 * has no mod_deflate/gzip configured, so large list payloads (e.g.
 * /api/tickets, ~2MB uncompressed) were being sent over the wire raw.
 * Applied at the app layer instead of web-server config so it works the
 * same regardless of how/where this app is deployed.
 */
class CompressJsonResponse
{
    private const MIN_BYTES = 1024;

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!str_contains((string) $request->header('Accept-Encoding'), 'gzip')) {
            return $response;
        }

        if (!str_contains((string) $response->headers->get('Content-Type'), 'application/json')) {
            return $response;
        }

        if ($response->headers->get('Content-Encoding')) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || strlen($content) < self::MIN_BYTES) {
            return $response;
        }

        $compressed = gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', (string) strlen($compressed));
        $response->headers->set('Vary', 'Accept-Encoding');

        return $response;
    }
}
