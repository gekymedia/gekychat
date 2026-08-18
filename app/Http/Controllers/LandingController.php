<?php

namespace App\Http\Controllers;

use App\Services\AppVersionService;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function __construct(private readonly AppVersionService $appVersions) {}

    /**
     * Show the landing page
     */
    public function index()
    {
        return view('landing.index');
    }

    /**
     * Desktop & mobile download page (public beta installers).
     */
    public function download()
    {
        $platforms = $this->appVersions->allForAdmin();
        $defaults = config('app_versions.platforms', []);

        foreach (AppVersionService::PLATFORMS as $platform) {
            if (empty($platforms[$platform]['download_url'])) {
                $fallback = $defaults[$platform]['download_url'] ?? null;
                if ($fallback) {
                    $platforms[$platform]['download_url'] = $fallback;
                }
            }
        }

        return view('landing.download', [
            'platforms' => $platforms,
            'labels' => AppVersionService::platformLabels(),
        ]);
    }

    /**
     * Show features page
     */
    public function features()
    {
        return view('landing.features');
    }

    /**
     * Show pricing page
     */
    public function pricing()
    {
        return view('landing.pricing');
    }

    /**
     * Show documentation page
     */
    public function docs()
    {
        return view('landing.docs');
    }

    /**
     * Help center (FAQs and guides)
     */
    public function help()
    {
        return view('pages.help');
    }

    /**
     * Contact / support page
     */
    public function contact()
    {
        return view('pages.contact');
    }

    /**
     * Redirect to chat login
     */
    public function login()
    {
        return redirect('https://chat.gekychat.com/login');
    }
}

