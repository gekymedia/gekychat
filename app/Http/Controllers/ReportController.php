<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Report a user (Web Route)
     */
    public function store(Request $request, $userId)
    {
        $validated = $request->validate([
            'reason'  => 'required|string|max:100',
            'details' => 'nullable|string|max:500',
            'block'   => 'nullable|boolean',
        ]);

        $target = User::findOrFail($userId);
        $reporter = Auth::user();

        if ($reporter->id === $target->id) {
            if ($request->wantsJson() || $request->ajax()) {
                return response()->json(['error' => 'You cannot report yourself'], 422);
            }
            return back()->withErrors(['error' => 'You cannot report yourself']);
        }

        $report = Report::create([
            'reporter_id'     => $reporter->id,
            'reported_user_id'=> $target->id,
            'reason'          => $validated['reason'],
            'details'         => $validated['details'] ?? null,
        ]);

        // Optionally block the user as well
        if ($request->boolean('block')) {
            \App\Models\Block::firstOrCreate([
                'blocker_id' => $reporter->id,
                'blocked_user_id' => $target->id,
            ], [
                'reason' => 'Reported: ' . $validated['reason']
            ]);
        }

        if ($request->wantsJson() || $request->ajax()) {
            return response()->json(['success' => true, 'message' => 'Report submitted']);
        }

        return back()->with('status', 'Report submitted successfully');
    }
}
