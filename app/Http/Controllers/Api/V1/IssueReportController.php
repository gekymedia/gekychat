<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\IssueReport;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class IssueReportController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'category' => 'required|in:bug,crash,other',
            'description' => 'required|string|min:10|max:5000',
            'source' => 'nullable|in:shake,settings',
            'app_version' => 'nullable|string|max:64',
            'platform' => 'nullable|string|max:32',
            'device_model' => 'nullable|string|max:128',
            'os_version' => 'nullable|string|max:64',
            'screen_name' => 'nullable|string|max:255',
            'diagnostics' => 'nullable|string|max:10000',
            'screenshot' => 'nullable|image|max:5120',
        ]);

        $diagnostics = null;
        if (!empty($data['diagnostics'])) {
            $decoded = json_decode($data['diagnostics'], true);
            if (is_array($decoded)) {
                $diagnostics = $decoded;
            }
        }

        $screenshotPath = null;
        if ($request->hasFile('screenshot')) {
            $screenshotPath = $request->file('screenshot')->store('issue-reports', 'public');
        }

        $report = IssueReport::create([
            'user_id' => $request->user()->id,
            'category' => $data['category'],
            'description' => $data['description'],
            'source' => $data['source'] ?? 'settings',
            'app_version' => $data['app_version'] ?? null,
            'platform' => $data['platform'] ?? null,
            'device_model' => $data['device_model'] ?? null,
            'os_version' => $data['os_version'] ?? null,
            'screen_name' => $data['screen_name'] ?? null,
            'diagnostics' => $diagnostics,
            'screenshot_path' => $screenshotPath,
            'status' => 'pending',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Thank you — your report was sent to our team.',
            'data' => [
                'id' => $report->id,
                'created_at' => $report->created_at->toIso8601String(),
            ],
        ], 201);
    }
}
