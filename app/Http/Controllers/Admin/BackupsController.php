<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\BackupMonitorService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class BackupsController extends Controller
{
    public function index(BackupMonitorService $monitor): View
    {
        return view('admin.backups.index', [
            'status' => $monitor->snapshot(),
        ]);
    }

    public function status(BackupMonitorService $monitor): JsonResponse
    {
        return response()->json($monitor->snapshot());
    }
}
