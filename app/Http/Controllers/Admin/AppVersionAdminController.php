<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AppVersionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AppVersionAdminController extends Controller
{
    public function __construct(private readonly AppVersionService $appVersions) {}

    public function index(): View
    {
        return view('admin.app_versions.index', [
            'platforms' => $this->appVersions->allForAdmin(),
            'androidInAppUpdate' => $this->appVersions->androidInAppUpdateSettings(),
            'platformLabels' => AppVersionService::platformLabels(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $versionRule = ['required', 'string', 'regex:/^\d+\.\d+\.\d+(\+\d+)?$/'];
        $urlRule = ['nullable', 'string', 'max:2048'];

        $rules = [
            'android_in_app_update_enabled' => ['nullable', 'boolean'],
            'android_in_app_update_priority' => ['nullable', 'in:flexible,immediate'],
        ];

        foreach (AppVersionService::PLATFORMS as $platform) {
            $rules["{$platform}_latest_version"] = $versionRule;
            $rules["{$platform}_min_version"] = $versionRule;
            $rules["{$platform}_download_url"] = $urlRule;
        }

        $validated = $request->validate($rules);

        $this->appVersions->saveAdminSettings($validated);

        return redirect()
            ->route('admin.app-versions.index')
            ->with('success', 'App version settings saved. Clients will see changes on their next version check.');
    }
}
