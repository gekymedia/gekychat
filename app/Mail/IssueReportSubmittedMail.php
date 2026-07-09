<?php

namespace App\Mail;

use App\Models\IssueReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IssueReportSubmittedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public IssueReport $report) {}

    public function build()
    {
        $user = $this->report->user;
        $userLabel = $user?->name ?? $user?->phone ?? ('User #' . $this->report->user_id);

        return $this->subject('[GekyChat] New issue report #' . $this->report->id)
            ->view('emails.issue-report-submitted')
            ->with([
                'report' => $this->report,
                'userLabel' => $userLabel,
                'adminUrl' => url('/admin/issue-reports/' . $this->report->id),
            ]);
    }
}
