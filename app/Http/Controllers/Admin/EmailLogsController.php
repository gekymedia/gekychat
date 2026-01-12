<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmailLogsController extends Controller
{
    /**
     * Display email logs index page
     * GET /admin/email-logs
     */
    public function index(Request $request)
    {
        $query = EmailLog::with(['routedToUser:id,name,username,phone'])
            ->orderBy('processed_at', 'desc');

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by failed emails
        if ($request->boolean('failed_only')) {
            $query->where('status', 'failed');
        }

        // Search by email address or username
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('from_email', 'like', "%{$search}%")
                  ->orWhere('routed_to_username', 'like', "%{$search}%")
                  ->orWhere('subject', 'like', "%{$search}%");
            });
        }

        // Date range filter
        if ($request->filled('date_from')) {
            $query->where('processed_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->where('processed_at', '<=', $request->date_to . ' 23:59:59');
        }

        $logs = $query->paginate(50);

        // Statistics
        $stats = [
            'total' => EmailLog::count(),
            'successful' => EmailLog::where('status', 'success')->count(),
            'failed' => EmailLog::where('status', 'failed')->count(),
            'ignored' => EmailLog::where('status', 'ignored')->count(),
            'today' => EmailLog::whereDate('processed_at', today())->count(),
            'last_7_days' => EmailLog::where('processed_at', '>=', now()->subDays(7))->count(),
        ];

        return view('admin.email-logs.index', compact('logs', 'stats'));
    }

    /**
     * Show email log details
     * GET /admin/email-logs/{id}
     */
    public function show($id)
    {
        $log = EmailLog::with(['routedToUser', 'conversation', 'message'])
            ->findOrFail($id);

        return view('admin.email-logs.show', compact('log'));
    }

    /**
     * Get email logs as JSON (for AJAX)
     * GET /admin/email-logs/data
     */
    public function data(Request $request)
    {
        $query = EmailLog::with(['routedToUser:id,name,username,phone'])
            ->orderBy('processed_at', 'desc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('from_email', 'like', "%{$search}%")
                  ->orWhere('routed_to_username', 'like', "%{$search}%");
            });
        }

        $logs = $query->paginate(50);

        return response()->json($logs);
    }
}
