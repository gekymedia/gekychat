<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppVersionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppVersionDeployController extends Controller
{
    public function __construct(private readonly AppVersionService $appVersions) {}

    /**
     * PATCH /api/v1/app/version/latest
     * Bearer APP_VERSION_DEPLOY_TOKEN — called by mobile/desktop deploy scripts after store upload.
     */
    public function updateLatest(Request $request): JsonResponse
    {
        $expected = (string) config('app_versions.deploy_token', '');
        $token = (string) $request->bearerToken();

        if ($expected === '' || ! hash_equals($expected, $token)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $validated = $request->validate([
            'platform' => 'required|in:android,ios,windows,macos,linux',
            'latest_version' => ['required', 'string', 'regex:/^\d+\.\d+\.\d+(\+\d+)?$/'],
        ]);

        $payload = $this->appVersions->updateLatestVersion(
            $validated['platform'],
            $validated['latest_version']
        );

        return response()->json([
            'data' => $payload,
            'message' => 'Latest version updated. Clients will see changes on their next version check.',
        ]);
    }
}
