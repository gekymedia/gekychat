<?php

namespace App\Http\Controllers;

use App\Services\InAppNoticeService;
use Illuminate\Http\Request;

class InAppNoticeWebController extends Controller
{
    public function __construct(
        private readonly InAppNoticeService $notices,
    ) {}

    public function dismiss(Request $request)
    {
        $request->validate([
            'notice_key' => 'required|string|max:190',
        ]);

        $this->notices->dismiss($request->user(), $request->string('notice_key')->toString());

        return response()->json(['ok' => true]);
    }
}
