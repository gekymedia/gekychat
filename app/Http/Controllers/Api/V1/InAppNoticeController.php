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
        $items = $this->notices->activeForUser($user);

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
