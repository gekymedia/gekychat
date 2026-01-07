<?php

namespace App\Services;

use App\Models\User;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\EmailThread;
use App\Models\EmailMessage;
use App\Models\EmailUserMapping;
use App\Services\FeatureFlagService;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * PHASE 2: Email Service
 * 
 * Handles email ingestion and routing for Email-Chat.
 */
class EmailService
{
    /**
     * Extract username from email address
     * Format: mail+{username}@gekychat.com
     */
    public function extractUsernameFromEmail(string $email): ?string
    {
        if (!str_contains($email, '@gekychat.com')) {
            return null;
        }

        // Extract the part before @
        $localPart = explode('@', $email)[0];
        
        // Check if it matches mail+{username} format
        if (!str_starts_with($localPart, 'mail+')) {
            return null;
        }

        // Extract username
        $username = substr($localPart, 5); // Remove 'mail+'
        
        return $username ?: null;
    }

    /**
     * Find user by username (for email routing)
     */
    public function findUserByUsername(string $username): ?User
    {
        return User::where('username', $username)->first();
    }

    /**
     * Process incoming email and create chat message
     */
    public function processIncomingEmail(array $emailData): ?Message
    {
        try {
            // Extract recipient email (should be mail+username@gekychat.com)
            $toEmails = $emailData['to'] ?? [];
            $username = null;
            $user = null;

            foreach ($toEmails as $email) {
                $emailAddr = is_array($email) ? ($email['address'] ?? $email) : $email;
                $extractedUsername = $this->extractUsernameFromEmail($emailAddr);
                
                if ($extractedUsername) {
                    $username = $extractedUsername;
                    $user = $this->findUserByUsername($username);
                    break;
                }
            }

            if (!$user) {
                Log::warning('Email received for unknown username', ['email' => $emailData]);
                return null; // Username doesn't exist - reject gracefully
            }

            // Check if user has email_chat feature enabled
            if (!FeatureFlagService::isEnabled('email_chat', $user)) {
                Log::warning('Email received but user does not have email_chat enabled', ['user_id' => $user->id]);
                return null;
            }

            // Extract sender information
            $fromEmail = is_array($emailData['from']) 
                ? (is_array($emailData['from'][0]) ? $emailData['from'][0] : ['address' => $emailData['from'][0], 'name' => null])
                : ['address' => $emailData['from'], 'name' => null];
            
            $fromAddress = $fromEmail['address'] ?? $emailData['from'];
            $fromName = $fromEmail['name'] ?? null;

            // Find or create conversation for this email thread
            $conversation = $this->findOrCreateEmailConversation($user->id, $fromAddress, $emailData);

            // Extract subject for message metadata
            $subject = $emailData['subject'] ?? '';

            // Clean HTML body (strip scripts, keep readable content)
            $htmlBody = $emailData['html_body'] ?? null;
            $textBody = $emailData['text_body'] ?? $emailData['body'] ?? '';
            
            // Convert HTML to readable text if needed
            if ($htmlBody && !$textBody) {
                $textBody = strip_tags($htmlBody);
                $textBody = html_entity_decode($textBody, ENT_QUOTES, 'UTF-8');
            }

            // Format message body with subject (subject shown at top, bold in UI)
            $messageBody = $subject ? "**{$subject}**\n\n{$textBody}" : $textBody;

            // Create message
            $message = Message::create([
                'conversation_id' => $conversation->id,
                'sender_id' => null, // External email sender (no GekyChat user)
                'sender_type' => 'email', // PHASE 2: Email source type
                'body' => $messageBody,
                'type' => 'text',
                'metadata' => [
                    'email_source' => true,
                    'email_subject' => $subject,
                    'email_from_address' => $fromAddress,
                    'email_from_name' => $fromName,
                ],
            ]);

            // Create email thread entry
            $emailThread = EmailThread::firstOrCreate(
                ['thread_id' => $emailData['message_id'] ?? Str::random(32)],
                [
                    'conversation_id' => $conversation->id,
                    'subject' => $subject,
                    'participants' => [
                        'from' => ['address' => $fromAddress, 'name' => $fromName],
                        'to' => $toEmails,
                    ],
                    'last_email_at' => now(),
                    'email_count' => 1,
                ]
            );

            // Create email message record
            EmailMessage::create([
                'message_id' => $message->id,
                'email_thread_id' => $emailThread->id,
                'message_id_header' => $emailData['message_id'] ?? Str::random(32),
                'in_reply_to' => $emailData['in_reply_to'] ?? null,
                'references' => $emailData['references'] ?? null,
                'from_email' => ['address' => $fromAddress, 'name' => $fromName],
                'to_emails' => $toEmails,
                'cc_emails' => $emailData['cc'] ?? null,
                'bcc_emails' => $emailData['bcc'] ?? null,
                'html_body' => $htmlBody,
                'text_body' => $textBody,
                'status' => 'delivered',
                'delivered_at' => now(),
            ]);

            // Handle attachments if present
            if (isset($emailData['attachments']) && is_array($emailData['attachments'])) {
                // TODO: Process and attach email attachments
            }

            // Broadcast message to user (real-time)
            broadcast(new \App\Events\MessageSent($message))->toOthers();

            return $message;
        } catch (\Exception $e) {
            Log::error('Failed to process incoming email: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString(),
                'email_data' => $emailData,
            ]);
            return null;
        }
    }

    /**
     * Find or create conversation for email thread
     */
    private function findOrCreateEmailConversation(int $userId, string $fromEmail, array $emailData): Conversation
    {
        // Check if we have an existing email thread for this sender
        $existingThread = EmailThread::whereHas('emailMessages', function ($query) use ($fromEmail) {
            $query->whereJsonContains('from_email->address', $fromEmail);
        })->where('conversation_id', function ($query) use ($userId) {
            $query->select('id')
                ->from('conversations')
                ->whereJsonContains('metadata->email_conversation', true)
                ->whereHas('members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                ->limit(1);
        })->first();

        if ($existingThread) {
            return $existingThread->conversation;
        }

        // Check if sender email maps to a GekyChat user
        $senderUser = EmailUserMapping::where('email', $fromEmail)->first()?->user;

        // Create new conversation
        $conversation = Conversation::create([
            'name' => $emailData['from_name'] ?? $fromEmail,
            'metadata' => [
                'email_conversation' => true,
                'email_address' => $fromEmail,
            ],
        ]);

        // Add recipient user
        $conversation->members()->attach($userId);

        // If sender is a GekyChat user, add them too
        if ($senderUser) {
            $conversation->members()->attach($senderUser->id);
        }

        return $conversation;
    }

    /**
     * Send reply email
     */
    public function sendReplyEmail(Message $replyMessage, EmailMessage $originalEmail): bool
    {
        try {
            $fromEmail = $originalEmail->to_emails[0]['address'] ?? null;
            if (!$fromEmail) {
                return false;
            }

            // Extract username from reply sender
            $sender = $replyMessage->conversation->members()->where('user_id', '!=', $originalEmail->message->sender_id)->first();
            if (!$sender || !$sender->username) {
                return false;
            }

            $replyFromEmail = "mail+{$sender->username}@gekychat.com";
            $replyToEmail = $originalEmail->from_email['address'];

            // Get reply body (remove subject if present)
            $replyBody = $replyMessage->body;
            if (str_contains($replyBody, '**')) {
                $parts = explode('**', $replyBody, 3);
                $replyBody = $parts[2] ?? $parts[1] ?? $replyBody;
            }

            // Send email reply (preserve threading headers)
            Mail::send([], [], function ($message) use ($replyFromEmail, $replyToEmail, $replyBody, $originalEmail) {
                $message->from($replyFromEmail, 'GekyChat')
                    ->to($replyToEmail)
                    ->subject('Re: ' . ($originalEmail->emailThread->subject ?? 'Message'))
                    ->setBody($replyBody, 'text/plain')
                    ->getHeaders()
                    ->addTextHeader('In-Reply-To', $originalEmail->message_id_header)
                    ->addTextHeader('References', ($originalEmail->references ?? '') . ' ' . $originalEmail->message_id_header);
            });

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to send email reply: ' . $e->getMessage());
            return false;
        }
    }
}

