<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\AppVersionService;
use App\Support\AppVersionComparator;
use Illuminate\Http\Request;

class AppVersionController extends Controller
{
    public function __construct(private readonly AppVersionService $appVersions) {}

    /**
     * GET /api/v1/app/version?platform=android|ios|windows|macos|linux
     * Optional: current_version=1.0.0+89 — server adds update_status hints.
     */
    public function show(Request $request)
    {
        $platform = (string) $request->query('platform', '');
        $payload = $this->appVersions->forPlatform($platform);

        $current = trim((string) $request->query('current_version', ''));
        if ($current !== '') {
            $payload['current_version'] = $current;
            $payload['update_required'] = AppVersionComparator::isLessThan(
                $current,
                $payload['min_version']
            );
            $payload['update_available'] = AppVersionComparator::isLessThan(
                $current,
                $payload['latest_version']
            );
            $payload['is_latest'] = ! $payload['update_available'];
        }

        return response()->json(['data' => $payload]);
    }
}
