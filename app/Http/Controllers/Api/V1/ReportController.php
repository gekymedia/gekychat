<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Controller to handle reporting users and admin review of reports.
 */
class ReportController extends Controller
{
    /**
     * File a report against a user. The reporting user can specify a reason
     * (e.g. spam, abuse) and provide details. Optionally block the user.
     */
    public function report(Request $request, $userId)
    {
        $request->validate([
            'reason'  => 'required|string|max:100',
            'details' => 'nullable|string|max:500',
            'block'   => 'nullable|boolean',
        ]);

        $target = User::findOrFail($userId);
        $reporter = $request->user();

        if ($reporter->id === $target->id) {
            return response()->json(['error' => 'You cannot report yourself'], 422);
        }

        $report = Report::create([
            'reporter_id'     => $reporter->id,
            'reported_user_id'=> $target->id,
            'reason'          => $request->reason,
            'details'         => $request->details,
        ]);

        // Optionally block the user as well
        if ($request->boolean('block')) {
            $reporter->blockedUsers()->syncWithoutDetaching([$target->id => ['reason' => 'Reported: ' . $request->reason]]);
        }

        return response()->json(['message' => 'Report submitted']);
    }

    /**
     * List all reports for admin review.
     */
    public function index(Request $request)
    {
        // Ensure user has admin permission
        if (!$request->user()->is_admin) {
            abort(403);
        }

        $reports = Report::with(['reporter:id,name,phone', 'reportedUser:id,name,phone'])
            ->orderByDesc('created_at')
            ->get();
        return response()->json(['data' => $reports]);
    }

    /**
     * Update a report status and optionally ban the reported user until a date.
     */
    public function update(Request $request, $reportId)
    {
        if (!$request->user()->is_admin) {
            abort(403);
        }

        $request->validate([
            'status'      => 'required|string|in:pending,resolved,dismissed',
            'ban_days'    => 'nullable|integer|min:1|max:365',
        ]);

        $report = Report::findOrFail($reportId);
        $report->status = $request->status;

        if ($request->filled('ban_days')) {
            $report->banned_until = now()->addDays((int)$request->ban_days);
            // Optionally mark the reported user as banned via some column or event
            $reportedUser = $report->reportedUser;
            $reportedUser->update(['status' => 'banned']);
        }

        $report->save();
        return response()->json(['message' => 'Report updated']);
    }
}