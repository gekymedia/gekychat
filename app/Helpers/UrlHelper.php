<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class UrlHelper
{
    /**
     * Get a secure storage URL (forces HTTPS if request is HTTPS)
     *
     * @param string $path
     * @param string|null $disk
     * @return string
     */
    public static function secureStorageUrl(string $path, ?string $disk = 'public'): string
    {
        // Check if path is already a full URL
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        
        // Get base URL from config
        $appUrl = rtrim(config('app.url', ''), '/');
        
        // Generate storage URL
        $url = Storage::disk($disk)->url($path);
        
        // If Storage::url() returns a relative URL, make it absolute
        if (!str_starts_with($url, 'http')) {
            $url = $appUrl . $url;
        }
        
        // Force HTTPS if the current request is over HTTPS
        if (self::shouldForceHttps() && str_starts_with($url, 'http://')) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        return $url;
    }

    /**
     * Get a secure asset URL (forces HTTPS if request is HTTPS)
     *
     * @param string $path
     * @return string
     */
    public static function secureAsset(string $path): string
    {
        $url = asset($path);
        
        // Force HTTPS if the current request is over HTTPS
        if (self::shouldForceHttps() && str_starts_with($url, 'http://')) {
            $url = str_replace('http://', 'https://', $url);
        }
        
        return $url;
    }

    /**
     * Check if we should force HTTPS based on the current request
     *
     * @return bool
     */
    private static function shouldForceHttps(): bool
    {
        // Check if request is secure (direct HTTPS)
        if (request()->secure()) {
            return true;
        }
        
        // Check for proxy headers (common in production setups behind load balancers)
        if (request()->header('X-Forwarded-Proto') === 'https') {
            return true;
        }
        
        // Check for other common proxy headers
        if (request()->header('X-Forwarded-Ssl') === 'on') {
            return true;
        }
        
        // Check if APP_URL is HTTPS (indicates production HTTPS setup)
        $appUrl = config('app.url', '');
        if (str_starts_with($appUrl, 'https://')) {
            return true;
        }
        
        return false;
    }
}
