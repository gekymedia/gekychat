<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\IssueReport;
use App\Services\IssueReportReplyService;
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

    public function show(IssueReport $issueReport)
    {
        $issueReport->load(['user:id,name,phone,email,created_at', 'repliedBy:id,name']);

        return view('admin.issue_reports.show', [
            'report' => $issueReport,
        ]);
    }

    public function update(Request $request, IssueReport $issueReport)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,reviewed,resolved',
            'admin_notes' => 'nullable|string|max:10000',
        ]);

        $issueReport->update($data);

        return redirect()
            ->route('admin.issue-reports.show', $issueReport)
            ->with('success', 'Report updated.');
    }

    public function reply(Request $request, IssueReport $issueReport, IssueReportReplyService $replyService)
    {
        $data = $request->validate([
            'admin_reply' => 'required|string|min:3|max:5000',
        ]);

        $replyService->sendReply($issueReport, $data['admin_reply'], $request->user());

        return redirect()
            ->route('admin.issue-reports.show', $issueReport)
            ->with('success', 'Reply sent to the user (push and email if available).');
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
