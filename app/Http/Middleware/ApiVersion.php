<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiVersion
{
    private const SUPPORTED_VERSIONS = ['v1', 'v2'];
    private const DEFAULT_VERSION = 'v1';
    
    public function handle(Request $request, Closure $next, ?string $version = null): Response
    {
        $apiVersion = $version ?? $this->detectVersion($request);
        
        if (!in_array($apiVersion, self::SUPPORTED_VERSIONS)) {
            return response()->json([
                'error' => 'API version not supported',
                'supported_versions' => self::SUPPORTED_VERSIONS,
                'requested_version' => $apiVersion,
            ], 400);
        }
        
        $request->attributes->set('api_version', $apiVersion);
        
        // Add deprecation warning for v1
        $response = $next($request);
        
        if ($apiVersion === 'v1') {
            $response->headers->set('Deprecation', 'true');
            $response->headers->set('Sunset', 'Sat, 31 Dec 2026 23:59:59 GMT');
            $response->headers->set('Link', '<https://api.gekychat.com/v2/docs>; rel="successor-version"');
        }
        
        return $response;
    }
    
    private function detectVersion(Request $request): string
    {
        // 1. Check URL path
        if (preg_match('#/api/(v\d+)/#', $request->path(), $matches)) {
            return $matches[1];
        }
        
        // 2. Check Accept header
        $accept = $request->header('Accept');
        if ($accept && preg_match('/vnd\.gekychat\.(v\d+)\+json/', $accept, $matches)) {
            return $matches[1];
        }
        
        // 3. Check custom header
        if ($request->hasHeader('X-API-Version')) {
            return $request->header('X-API-Version');
        }
        
        return self::DEFAULT_VERSION;
    }
}
