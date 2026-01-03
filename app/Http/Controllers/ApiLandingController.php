<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ApiLandingController extends Controller
{
    /**
     * Show the API landing page (like api.whatsapp.com)
     * This page prompts users to open the app or download it
     */
    public function index(Request $request)
    {
        // If request wants JSON explicitly (API client), return JSON
        // Check for Accept header or if path includes /api/
        if ($request->wantsJson() || str_contains($request->path(), 'api/')) {
            return response()->json([
                'name' => 'GekyChat API',
                'version' => '1.0.0',
                'status' => 'active',
                'endpoints' => [
                    'v1' => '/api/v1',
                    'platform' => '/api/platform',
                ],
                'documentation' => '/api/docs',
            ]);
        }

        // Detect user agent
        $userAgent = $request->userAgent() ?? '';
        $isMobile = $this->isMobileDevice($userAgent);
        $isAndroid = $this->isAndroid($userAgent);
        $isIOS = $this->isIOS($userAgent);
        $isWindows = $this->isWindows($userAgent);
        $isMacOS = $this->isMacOS($userAgent);
        $isLinux = $this->isLinux($userAgent);
        $isDesktop = !$isMobile && ($isWindows || $isMacOS || $isLinux);

        // App deep link (you can customize this based on your app's scheme)
        $appScheme = 'gekychat://';
        $appDeepLink = $appScheme . 'open';
        
        // App store URLs (update these with your actual app store links)
        $playStoreUrl = config('app.play_store_url', 'https://play.google.com/store/apps/details?id=com.gekychat.app');
        $appStoreUrl = config('app.app_store_url', 'https://apps.apple.com/app/gekychat/id123456789');
        
        // Desktop app download URLs
        $windowsUrl = config('app.windows_download_url', 'https://github.com/gekychat/desktop/releases/download/latest/GekyChat-Setup.exe');
        $macOSUrl = config('app.macos_download_url', 'https://github.com/gekychat/desktop/releases/download/latest/GekyChat.dmg');
        $linuxUrl = config('app.linux_download_url', 'https://github.com/gekychat/desktop/releases/download/latest/gekychat_amd64.deb');

        return view('api.landing', [
            'isMobile' => $isMobile,
            'isDesktop' => $isDesktop,
            'isAndroid' => $isAndroid,
            'isIOS' => $isIOS,
            'isWindows' => $isWindows,
            'isMacOS' => $isMacOS,
            'isLinux' => $isLinux,
            'appDeepLink' => $appDeepLink,
            'playStoreUrl' => $playStoreUrl,
            'appStoreUrl' => $appStoreUrl,
            'windowsUrl' => $windowsUrl,
            'macOSUrl' => $macOSUrl,
            'linuxUrl' => $linuxUrl,
        ]);
    }

    /**
     * Detect if the request is from a mobile device
     */
    private function isMobileDevice($userAgent): bool
    {
        return preg_match('/(android|iphone|ipad|mobile|webos|blackberry|windows phone)/i', $userAgent);
    }

    /**
     * Detect if the request is from Android
     */
    private function isAndroid($userAgent): bool
    {
        return preg_match('/android/i', $userAgent);
    }

    /**
     * Detect if the request is from iOS
     */
    private function isIOS($userAgent): bool
    {
        return preg_match('/(iphone|ipad|ipod)/i', $userAgent);
    }

    /**
     * Detect if the request is from Windows
     */
    private function isWindows($userAgent): bool
    {
        return preg_match('/windows/i', $userAgent);
    }

    /**
     * Detect if the request is from macOS
     */
    private function isMacOS($userAgent): bool
    {
        return preg_match('/(macintosh|mac os x|mac_powerpc)/i', $userAgent) && !$this->isIOS($userAgent);
    }

    /**
     * Detect if the request is from Linux
     */
    private function isLinux($userAgent): bool
    {
        return preg_match('/linux/i', $userAgent) && !preg_match('/android/i', $userAgent);
    }
}

