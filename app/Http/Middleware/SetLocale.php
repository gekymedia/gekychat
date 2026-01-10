<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $this->getLocale($request);
        
        if ($locale && in_array($locale, config('app.supported_locales', ['en']))) {
            App::setLocale($locale);
        }
        
        return $next($request);
    }
    
    /**
     * Get the locale from various sources
     */
    private function getLocale(Request $request): ?string
    {
        // 1. Check query parameter
        if ($request->has('lang')) {
            return $request->input('lang');
        }
        
        // 2. Check user preference
        if ($request->user() && $request->user()->preferred_locale) {
            return $request->user()->preferred_locale;
        }
        
        // 3. Check session
        if ($request->session()->has('locale')) {
            return $request->session()->get('locale');
        }
        
        // 4. Check Accept-Language header
        $acceptLanguage = $request->header('Accept-Language');
        if ($acceptLanguage) {
            $locale = substr($acceptLanguage, 0, 2);
            return $locale;
        }
        
        return null;
    }
}
