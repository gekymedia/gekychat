<?php

namespace App\Http\Controllers;

use App\Services\BirthdayCelebrantService;
use Illuminate\Http\Request;

class BirthdayWebController extends Controller
{
    public function __construct(
        private readonly BirthdayCelebrantService $birthdays,
    ) {}

    public function summary(Request $request)
    {
        return response()->json([
            'data' => $this->birthdays->summaryForUser($request->user()),
        ]);
    }
}
