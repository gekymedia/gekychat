<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ✅ MODERN: GZIP compression for API responses
 * Reduces bandwidth and improves load times
 */
class GzipResponseMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (!function_exists('gzencode') || $request->header('Accept-Encoding') === null) {
            return $response;
        }

        $acceptEncoding = strtolower($request->header('Accept-Encoding', ''));
        if (strpos($acceptEncoding, 'gzip') === false) {
            return $response;
        }

        $content = $response->getContent();
        if ($content === false || $content === '' || strlen($content) < 256) {
            return $response;
        }

        $contentType = $response->headers->get('Content-Type', '');
        $compressible = str_contains($contentType, 'application/json')
            || str_contains($contentType, 'text/')
            || str_contains($contentType, 'application/javascript');
        if (!$compressible) {
            return $response;
        }

        $compressed = @gzencode($content, 6);
        if ($compressed === false) {
            return $response;
        }

        $response->setContent($compressed);
        $response->headers->set('Content-Encoding', 'gzip');
        $response->headers->set('Content-Length', strlen($compressed));
        $response->headers->remove('Transfer-Encoding');

        return $response;
    }
}
