<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\EmailService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * PHASE 2: Email Webhook Controller
 * 
 * Receives incoming emails via webhook (e.g., from Mailgun, SendGrid, etc.)
 */
class EmailWebhookController extends Controller
{
    public function __construct(private EmailService $emailService)
    {
    }

    /**
     * Handle incoming email webhook
     * POST /webhook/email/incoming
     */
    public function incoming(Request $request)
    {
        try {
            // Parse email data from webhook
            // Format depends on email service provider (Mailgun, SendGrid, etc.)
            $emailData = $this->parseWebhookData($request);

            // Process email and create chat message
            $message = $this->emailService->processIncomingEmail($emailData);

            if ($message) {
                return response()->json(['status' => 'success', 'message_id' => $message->id], 200);
            }

            return response()->json(['status' => 'ignored'], 200);
        } catch (\Exception $e) {
            Log::error('Email webhook error: ' . $e->getMessage());
            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Parse webhook data (adapt based on email service provider)
     */
    private function parseWebhookData(Request $request): array
    {
        // Example for Mailgun format
        // Adjust based on your email service provider
        return [
            'message_id' => $request->input('Message-Id'),
            'from' => $request->input('from'),
            'to' => $request->input('To') ? explode(',', $request->input('To')) : [],
            'subject' => $request->input('subject'),
            'body' => $request->input('body-plain'),
            'html_body' => $request->input('body-html'),
            'cc' => $request->input('Cc') ? explode(',', $request->input('Cc')) : null,
            'bcc' => $request->input('Bcc') ? explode(',', $request->input('Bcc')) : null,
            'in_reply_to' => $request->input('In-Reply-To'),
            'references' => $request->input('References'),
            'attachments' => $request->input('attachments', []),
        ];
    }
}
