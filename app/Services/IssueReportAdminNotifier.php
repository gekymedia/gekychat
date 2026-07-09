<?php

namespace App\Services;

use App\Mail\IssueReportSubmittedMail;
use App\Models\IssueReport;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class IssueReportAdminNotifier
{
    public function __construct(
        private SmsServiceInterface $sms,
    ) {}

    public function isEnabled(): bool
    {
        return (bool) config('services.issue_reports.notify_enabled', true);
    }

    public function notifyNewReport(IssueReport $report): void
    {
        if (!$this->isEnabled()) {
            return;
        }

        $this->sendAdminEmails($report);
        $this->sendAdminSms($report);
        $this->sendSlack($report);
    }

    private function sendAdminEmails(IssueReport $report): void
    {
        $emails = config('services.issue_reports.notify_emails', []);
        if ($emails === []) {
            return;
        }

        foreach ($emails as $email) {
            try {
                Mail::to($email)->send(new IssueReportSubmittedMail($report));
            } catch (\Throwable $e) {
                Log::warning('Issue report admin email failed', [
                    'report_id' => $report->id,
                    'email' => $email,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendAdminSms(IssueReport $report): void
    {
        if (!config('services.issue_reports.sms_enabled', true)) {
            return;
        }

        $phones = config('services.issue_reports.notify_phones', []);
        if ($phones === []) {
            return;
        }

        $user = $report->user;
        $userLabel = $user?->name ?? $user?->phone ?? ('#' . $report->user_id);
        $adminPath = '/admin/issue-reports/' . $report->id;
        $snippet = Str::limit(preg_replace('/\s+/', ' ', $report->description), 80);

        $message = sprintf(
            'GekyChat issue #%d (%s/%s) from %s: %s. Open: %s',
            $report->id,
            $report->category,
            $report->source,
            $userLabel,
            $snippet,
            url($adminPath),
        );

        // Keep within typical single-SMS length when possible.
        if (strlen($message) > 320) {
            $message = sprintf(
                'GekyChat issue #%d (%s). %s. %s',
                $report->id,
                $report->category,
                $userLabel,
                url($adminPath),
            );
        }

        foreach ($phones as $phone) {
            try {
                $result = $this->sms->sendSms($phone, $message);
                if (!($result['success'] ?? false)) {
                    Log::warning('Issue report admin SMS failed', [
                        'report_id' => $report->id,
                        'phone' => $phone,
                        'error' => $result['error'] ?? 'unknown',
                    ]);
                }
            } catch (\Throwable $e) {
                Log::warning('Issue report admin SMS exception', [
                    'report_id' => $report->id,
                    'phone' => $phone,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function sendSlack(IssueReport $report): void
    {
        $webhook = config('services.issue_reports.slack_webhook');
        if (!$webhook) {
            return;
        }

        $user = $report->user;
        $adminUrl = url('/admin/issue-reports/' . $report->id);
        $userLabel = $user?->name ?? $user?->phone ?? ('User #' . $report->user_id);

        $text = implode("\n", [
            '*New issue report #' . $report->id . '*',
            'Category: `' . $report->category . '` · Source: `' . $report->source . '`',
            'From: ' . $userLabel . ' (#' . $report->user_id . ')',
            'Device: ' . ($report->platform ?? '—') . ' ' . ($report->app_version ?? ''),
            'Screen: ' . ($report->screen_name ?? '—'),
            '>' . Str::limit($report->description, 280),
            '<' . $adminUrl . '|Open in admin>',
        ]);

        try {
            Http::timeout(8)->post($webhook, ['text' => $text]);
        } catch (\Throwable $e) {
            Log::warning('Issue report Slack notify failed', [
                'report_id' => $report->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
