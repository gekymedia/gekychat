<?php

namespace App\Http\Controllers;

use App\Services\ProductAnalyticsIngestService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ApiLandingController extends Controller
{
    public function __construct(
        private ProductAnalyticsIngestService $analytics,
    ) {}

    /**
     * Show the API landing page (like api.whatsapp.com)
     * This page prompts users to open the app or download it
     */
    public function index(Request $request): View|\Illuminate\Http\JsonResponse
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

        $visitorPlatform = $this->visitorPlatform(
            $isIOS,
            $isAndroid,
            $isWindows,
            $isMacOS,
            $isLinux,
            $isMobile,
        );

        if (!$this->looksLikeBot($userAgent)) {
            try {
                $this->analytics->trackStoreLanding('page_view', $visitorPlatform, [
                    'path' => '/'.$request->path(),
                    'host' => $request->getHost(),
                    'referer' => $request->headers->get('referer'),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // App deep link (you can customize this based on your app's scheme)
        $appScheme = 'gekychat://';
        $appDeepLink = $appScheme.'open';

        // App store URLs (update these with your actual app store links)
        $playStoreUrl = config('app.play_store_url', 'https://play.google.com/store/apps/details?id=com.gekychat.app');
        $appStoreUrl = config('app.app_store_url', 'https://apps.apple.com/gh/app/gekychat/id6759990974');

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
            'playStoreUrl' => route('api.landing.download', ['platform' => 'android']),
            'appStoreUrl' => route('api.landing.download', ['platform' => 'ios']),
            'windowsUrl' => route('api.landing.download', ['platform' => 'windows']),
            'macOSUrl' => route('api.landing.download', ['platform' => 'macos']),
            'linuxUrl' => route('api.landing.download', ['platform' => 'linux']),
            'openAppTrackUrl' => route('api.landing.open-app'),
            // Keep raw URLs available if needed
            'rawPlayStoreUrl' => $playStoreUrl,
            'rawAppStoreUrl' => $appStoreUrl,
            'rawWindowsUrl' => $windowsUrl,
            'rawMacOSUrl' => $macOSUrl,
            'rawLinuxUrl' => $linuxUrl,
        ]);
    }

    /**
     * Record a store download click, then redirect to the real store / installer URL.
     */
    public function download(Request $request, string $platform): RedirectResponse
    {
        $platform = strtolower($platform);
        $map = [
            'ios' => config('app.app_store_url'),
            'android' => config('app.play_store_url'),
            'windows' => config('app.windows_download_url'),
            'macos' => config('app.macos_download_url'),
            'linux' => config('app.linux_download_url'),
        ];

        $target = $map[$platform] ?? config('app.app_store_url');
        $userAgent = $request->userAgent() ?? '';
        $visitorPlatform = $this->visitorPlatformFromUa($userAgent);

        if (!$this->looksLikeBot($userAgent)) {
            try {
                $this->analytics->trackStoreLanding('download_click', $visitorPlatform, [
                    'store' => $platform,
                    'referer' => $request->headers->get('referer'),
                ]);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return redirect()->away($target);
    }

    /**
     * Record “Open in GekyChat” taps (deep link) without blocking navigation.
     * Used via sendBeacon / fetch; returns 204.
     */
    public function openApp(Request $request)
    {
        $userAgent = $request->userAgent() ?? '';
        if (!$this->looksLikeBot($userAgent)) {
            try {
                $this->analytics->trackStoreLanding(
                    'open_app_click',
                    $this->visitorPlatformFromUa($userAgent),
                    ['referer' => $request->headers->get('referer')],
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return response()->noContent();
    }

    private function visitorPlatform(
        bool $isIOS,
        bool $isAndroid,
        bool $isWindows,
        bool $isMacOS,
        bool $isLinux,
        bool $isMobile,
    ): string {
        if ($isIOS) {
            return 'ios';
        }
        if ($isAndroid) {
            return 'android';
        }
        if ($isWindows) {
            return 'windows';
        }
        if ($isMacOS) {
            return 'macos';
        }
        if ($isLinux) {
            return 'linux';
        }
        if ($isMobile) {
            return 'mobile';
        }

        return 'web';
    }

    private function visitorPlatformFromUa(string $userAgent): string
    {
        return $this->visitorPlatform(
            $this->isIOS($userAgent),
            $this->isAndroid($userAgent),
            $this->isWindows($userAgent),
            $this->isMacOS($userAgent),
            $this->isLinux($userAgent),
            $this->isMobileDevice($userAgent),
        );
    }

    private function looksLikeBot(string $userAgent): bool
    {
        return (bool) preg_match(
            '/bot|crawl|spider|slurp|facebookexternalhit|preview|whatsapp|telegram|discord|slackbot|bingpreview/i',
            $userAgent,
        );
    }

    /**
     * Detect if the request is from a mobile device
     */
    private function isMobileDevice($userAgent): bool
    {
        return (bool) preg_match('/(android|iphone|ipad|mobile|webos|blackberry|windows phone)/i', $userAgent);
    }

    /**
     * Detect if the request is from Android
     */
    private function isAndroid($userAgent): bool
    {
        return (bool) preg_match('/android/i', $userAgent);
    }

    /**
     * Detect if the request is from iOS
     */
    private function isIOS($userAgent): bool
    {
        return (bool) preg_match('/(iphone|ipad|ipod)/i', $userAgent);
    }

    /**
     * Detect if the request is from Windows
     */
    private function isWindows($userAgent): bool
    {
        return (bool) preg_match('/windows/i', $userAgent) && !preg_match('/windows phone/i', $userAgent);
    }

    /**
     * Detect if the request is from macOS
     */
    private function isMacOS($userAgent): bool
    {
        return (bool) preg_match('/macintosh|mac os x/i', $userAgent) && !$this->isIOS($userAgent);
    }

    /**
     * Detect if the request is from Linux
     */
    private function isLinux($userAgent): bool
    {
        return (bool) preg_match('/linux/i', $userAgent) && !$this->isAndroid($userAgent);
    }
}
