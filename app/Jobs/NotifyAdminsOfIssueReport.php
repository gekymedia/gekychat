<?php

namespace App\Jobs;

use App\Models\IssueReport;
use App\Services\IssueReportAdminNotifier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyAdminsOfIssueReport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public int $issueReportId) {}

    public function handle(IssueReportAdminNotifier $notifier): void
    {
        $report = IssueReport::with('user:id,name,phone,email')->find($this->issueReportId);
        if (!$report) {
            return;
        }

        $notifier->notifyNewReport($report);
    }
}
