<?php

namespace App\Services;

use App\Mail\IssueReportReplyMail;
use App\Models\IssueReport;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class IssueReportReplyService
{
    public function __construct(private FcmService $fcm) {}

    public function sendReply(IssueReport $report, string $message, User $admin): IssueReport
    {
        $message = trim($message);
        if ($message === '') {
            throw new \InvalidArgumentException('Reply message cannot be empty.');
        }

        $report->update([
            'admin_reply' => $message,
            'admin_reply_at' => now(),
            'replied_by_user_id' => $admin->id,
            'status' => $report->status === 'pending' ? 'reviewed' : $report->status,
        ]);

        $user = $report->user;
        if ($user) {
            $this->sendPush($user->id, $report, $message);
            $this->sendEmail($user, $report, $message);
        }

        return $report->fresh(['user', 'repliedBy']);
    }

    private function sendPush(int $userId, IssueReport $report, string $message): void
    {
        try {
            $this->fcm->sendToUser($userId, [
                'title' => 'GekyChat Support',
                'body' => \Illuminate\Support\Str::limit($message, 180),
            ], [
                'type' => 'issue_report_reply',
                'issue_report_id' => (string) $report->id,
            ]);
        } catch (\Throwable $e) {
            Log::warning('Issue report reply push failed', [
                'report_id' => $report->id,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(User $user, IssueReport $report, string $message): void
    {
        if (!$user->email) {
            return;
        }

        try {
            Mail::to($user->email)->send(new IssueReportReplyMail($report, $message));
        } catch (\Throwable $e) {
            Log::warning('Issue report reply email failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
