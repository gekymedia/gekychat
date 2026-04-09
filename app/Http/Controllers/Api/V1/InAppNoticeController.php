<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\InAppNoticeService;
use Illuminate\Http\Request;

class InAppNoticeController extends Controller
{
    public function __construct(
        private readonly InAppNoticeService $notices,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $deviceStorageLowRaw = $request->query('device_storage_low', $request->header('X-Device-Storage-Low', '0'));
        $deviceStorageLow = in_array(strtolower((string) $deviceStorageLowRaw), ['1', 'true', 'yes', 'on'], true);
        $items = $this->notices->activeForUser($user, [
            'device_storage_low' => $deviceStorageLow,
        ]);

        return response()->json([
            'data' => $this->notices->toApiPayloads($items),
        ]);
    }

    public function dismiss(Request $request)
    {
        $request->validate([
            'notice_key' => 'required|string|max:190',
        ]);

        $this->notices->dismiss($request->user(), $request->string('notice_key')->toString());

        return response()->json(['ok' => true]);
    }
}
