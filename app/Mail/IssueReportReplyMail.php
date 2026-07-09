<?php

namespace App\Mail;

use App\Models\IssueReport;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class IssueReportReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public IssueReport $report,
        public string $replyMessage,
    ) {}

    public function build()
    {
        return $this->subject('Reply from GekyChat Support — report #' . $this->report->id)
            ->view('emails.issue-report-reply')
            ->with([
                'report' => $this->report,
                'replyMessage' => $this->replyMessage,
            ]);
    }
}
