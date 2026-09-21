<?php

namespace App\Http\Controllers;

use App\Services\AppVersionService;

class LandingController extends Controller
{
    public function __construct(private readonly AppVersionService $appVersions) {}

    public function index()
    {
        return view('home');
    }

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

    public function features()
    {
        return view('landing.features');
    }

    public function about()
    {
        return view('landing.about');
    }

    public function help()
    {
        return view('pages.help');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function login()
    {
        return redirect('https://web.gekychat.com/login');
    }
}
