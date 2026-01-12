<?php

namespace App\Services;

use App\Services\EmailService;
use Illuminate\Support\Facades\Log;
use Webklex\PHPIMAP\Client;
use Webklex\PHPIMAP\ClientManager;

/**
 * IMAP Email Service
 * 
 * Handles fetching emails from IMAP server and processing them
 */
class ImapEmailService
{
    private EmailService $emailService;
    private ?Client $client = null;

    public function __construct(EmailService $emailService)
    {
        $this->emailService = $emailService;
    }

    /**
     * Fetch emails from IMAP server
     * 
     * @param int $limit Maximum number of emails to fetch
     * @param bool $markSeen Whether to mark emails as seen
     * @return array Processed email count and errors
     */
    public function fetchEmails(int $limit = 200, bool $markSeen = false): array
    {
        $processed = 0;
        $errors = [];

        try {
            $client = $this->connect();
            if (!$client) {
                return ['processed' => 0, 'errors' => [['error' => 'Failed to connect to IMAP server']]];
            }

            $folder = $client->getFolder(env('MAIL_IMAP_FOLDER', 'INBOX'));
            if (!$folder) {
                $this->disconnect();
                return ['processed' => 0, 'errors' => [['error' => 'Failed to open IMAP folder']]];
            }

            // Get unread messages (newest first)
            $messages = $folder->query()->unseen()->limit($limit)->get();

            foreach ($messages as $message) {
                try {
                    // Check if already processed
                    $messageId = $message->getMessageId();
                    if ($messageId) {
                        $existing = \App\Models\EmailMessage::where('message_id_header', $messageId)->first();
                        if ($existing) {
                            continue; // Skip already processed
                        }
                    }

                    // Parse email data
                    $emailData = $this->parseMessage($message);
                    
                    if (!$emailData) {
                        continue;
                    }

                    // Process email using EmailService
                    $chatMessage = $this->emailService->processIncomingEmail($emailData);
                    
                    if ($chatMessage) {
                        $processed++;
                        
                        // Mark as seen if requested
                        if ($markSeen) {
                            $message->setFlag('Seen');
                        }
                    }
                } catch (\Exception $e) {
                    $errors[] = [
                        'message_id' => $message->getMessageId() ?? 'unknown',
                        'error' => $e->getMessage(),
                    ];
                    Log::error("Failed to process IMAP message: " . $e->getMessage(), [
                        'trace' => $e->getTraceAsString(),
                    ]);
                }
            }

            $this->disconnect();

            return [
                'processed' => $processed,
                'errors' => $errors,
            ];
        } catch (\Exception $e) {
            Log::error('IMAP fetch error: ' . $e->getMessage());
            $this->disconnect();
            return [
                'processed' => $processed,
                'errors' => array_merge($errors, [['error' => $e->getMessage()]]),
            ];
        }
    }

    /**
     * Parse message into email data array
     */
    private function parseMessage($message): ?array
    {
        try {
            $from = $message->getFrom();
            $to = $message->getTo();
            $cc = $message->getCc();
            $bcc = $message->getBcc();
            $subject = $message->getSubject();
            $textBody = $message->getTextBody();
            $htmlBody = $message->getHTMLBody();
            $messageId = $message->getMessageId();
            $inReplyTo = $message->getInReplyTo();
            $references = $message->getReferences();

            // Format addresses
            $fromArray = $this->formatAddress($from);
            $toArray = [];
            foreach ($to as $addr) {
                $toArray[] = $this->formatAddress($addr);
            }

            $ccArray = [];
            foreach ($cc as $addr) {
                $ccArray[] = $this->formatAddress($addr);
            }

            $bccArray = [];
            foreach ($bcc as $addr) {
                $bccArray[] = $this->formatAddress($addr);
            }

            // Extract attachments
            $attachments = [];
            foreach ($message->getAttachments() as $attachment) {
                $attachments[] = [
                    'filename' => $attachment->getName(),
                    'data' => $attachment->getContent(),
                    'size' => $attachment->getSize(),
                    'type' => $attachment->getContentType(),
                ];
            }

            return [
                'message_id' => $messageId,
                'from' => $fromArray,
                'to' => $toArray,
                'cc' => $ccArray,
                'bcc' => $bccArray,
                'subject' => $subject ?? '',
                'text_body' => $textBody ?? '',
                'html_body' => $htmlBody ?? '',
                'in_reply_to' => $inReplyTo,
                'references' => is_array($references) ? implode(' ', $references) : $references,
                'attachments' => $attachments,
                'date' => $message->getDate() ? $message->getDate()->format('Y-m-d H:i:s') : now()->toDateTimeString(),
            ];
        } catch (\Exception $e) {
            Log::error("Failed to parse IMAP message: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Format address object to array
     */
    private function formatAddress($address): array
    {
        if (is_string($address)) {
            return ['address' => $address, 'name' => null];
        }

        if (is_object($address)) {
            return [
                'address' => $address->mail ?? $address->personal ?? '',
                'name' => $address->personal ?? null,
            ];
        }

        return ['address' => '', 'name' => null];
    }

    /**
     * Connect to IMAP server
     * 
     * @return Client|false
     */
    public function connect()
    {
        try {
            $host = config('mail.imap.host') ?: env('MAIL_IMAP_HOST');
            $port = config('mail.imap.port') ?: env('MAIL_IMAP_PORT', 993);
            $encryption = config('mail.imap.encryption') ?: env('MAIL_IMAP_ENCRYPTION', 'ssl');
            $username = config('mail.imap.username') ?: env('MAIL_IMAP_USERNAME');
            $password = config('mail.imap.password') ?: env('MAIL_IMAP_PASSWORD');
            $validateCert = filter_var(env('MAIL_IMAP_VALIDATE_CERT', true), FILTER_VALIDATE_BOOLEAN);

            if (!$host || !$username || !$password) {
                Log::error('IMAP configuration missing');
                return false;
            }

            $cm = new ClientManager();
            $client = $cm->make([
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'validate_cert' => $validateCert,
                'username' => $username,
                'password' => $password,
                'protocol' => 'imap',
            ]);

            $client->connect();

            $this->client = $client;
            return $client;
        } catch (\Exception $e) {
            Log::error("IMAP connection failed: " . $e->getMessage(), [
                'host' => $host,
                'port' => $port,
                'encryption' => $encryption,
                'username' => $username,
                'trace' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * Close IMAP connection
     */
    public function disconnect(): void
    {
        if ($this->client) {
            try {
                $this->client->disconnect();
            } catch (\Exception $e) {
                // Ignore disconnect errors
            }
            $this->client = null;
        }
    }
}
