<?php

namespace App\Http\Controllers;

use App\Support\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeviceController extends Controller
{
    public function register(Request $r) {
        $data = $r->validate([
            'token'    => 'required|string|max:255',
            'platform' => 'required|in:android,ios',
        ]);

        DB::table('device_tokens')->updateOrInsert(
            ['user_id' => $r->user()->id, 'token' => $data['token']],
            ['platform' => $data['platform'], 'updated_at' => now(), 'created_at' => now()]
        );

        return ApiResponse::data(['ok'=>true]);
    }
}
