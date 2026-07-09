<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\BirthdayCelebrantService;
use Illuminate\Http\Request;

class BirthdayController extends Controller
{
    public function __construct(
        private readonly BirthdayCelebrantService $birthdays,
    ) {}

    public function summary(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'data' => $this->birthdays->summaryForUser($user),
        ]);
    }
}
