<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IssueReport;
use Illuminate\Http\Request;

class IssueReportAdminController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->get('status', 'pending');

        $reports = IssueReport::with('user:id,name,phone,email')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30)
            ->withQueryString();

        $counts = [
            'pending' => IssueReport::where('status', 'pending')->count(),
            'reviewed' => IssueReport::where('status', 'reviewed')->count(),
            'resolved' => IssueReport::where('status', 'resolved')->count(),
        ];

        return view('admin.issue_reports.index', [
            'reports' => $reports,
            'status' => $status,
            'counts' => $counts,
        ]);
    }

    public function updateStatus(Request $request, IssueReport $issueReport)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,reviewed,resolved',
        ]);

        $issueReport->update(['status' => $data['status']]);

        return back()->with('success', 'Report status updated.');
    }
}
