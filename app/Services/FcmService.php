<?php

namespace App\Services;

use App\Models\DeviceToken;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class FcmService
{
    protected $credentialsPath;
    protected $projectId;
    protected $fcmUrl;

    public function __construct()
    {
        $this->credentialsPath = config('services.fcm.credentials_path');
        $this->projectId = config('services.fcm.project_id');
        $this->fcmUrl = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";
    }

    /**
     * Get OAuth2 access token from service account
     */
    protected function getAccessToken(): ?string
    {
        try {
            if (!$this->credentialsPath || !file_exists(base_path($this->credentialsPath))) {
                Log::error('FCM credentials file not found', ['path' => $this->credentialsPath]);
                return null;
            }

            $credentials = json_decode(file_get_contents(base_path($this->credentialsPath)), true);
            
            if (!$credentials || !isset($credentials['private_key'])) {
                Log::error('Invalid FCM credentials file');
                return null;
            }

            $now = time();
            $expiry = $now + 3600;

            $payload = [
                'iss' => $credentials['client_email'],
                'sub' => $credentials['client_email'],
                'aud' => 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $expiry,
                'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            ];

            // Create JWT
            $header = base64_encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $payload = base64_encode(json_encode($payload));
            $signature = '';
            
            openssl_sign(
                $header . '.' . $payload,
                $signature,
                $credentials['private_key'],
                OPENSSL_ALGO_SHA256
            );
            
            $signature = base64_encode($signature);
            $jwt = $header . '.' . $payload . '.' . $signature;

            // Exchange JWT for access token
            $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $jwt,
            ]);

            if ($response->successful()) {
                return $response->json()['access_token'];
            }

            Log::error('Failed to get FCM access token', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('FCM access token exception', ['message' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Send notification to a user
     */
    public function sendToUser(int $userId, array $notification, array $data = []): bool
    {
        $tokens = DeviceToken::getTokensForUser($userId);

        if (empty($tokens)) {
            return false;
        }

        // V1 API sends to one token at a time
        $success = false;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $notification, $data)) {
                $success = true;
            }
        }

        return $success;
    }

    /**
     * Send notification to a single token (V1 API)
     */
    public function sendToToken(string $token, array $notification, array $data = []): bool
    {
        $accessToken = $this->getAccessToken();

        if (!$accessToken) {
            return false;
        }

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $notification['title'] ?? '',
                    'body' => $notification['body'] ?? '',
                ],
                'data' => $data,
                'android' => [
                    'priority' => 'high',
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                    ],
                ],
            ],
        ];

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                return true;
            }

            // Handle invalid token errors
            $error = $response->json();
            if (isset($error['error']['status']) && 
                in_array($error['error']['status'], ['NOT_FOUND', 'INVALID_ARGUMENT', 'UNREGISTERED'])) {
                DeviceToken::removeToken($token);
            }

            Log::error('FCM send failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('FCM exception', ['message' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Send notification to multiple tokens (legacy method for compatibility)
     */
    public function sendToTokens(array $tokens, array $notification, array $data = []): bool
    {
        $success = false;
        foreach ($tokens as $token) {
            if ($this->sendToToken($token, $notification, $data)) {
                $success = true;
            }
        }
        return $success;
    }

    /**
     * Send new message notification
     * @param string|null $attachmentMimeType Optional MIME type for media label (e.g. image/jpeg → "📷 Photo")
     */
    public function sendMessageNotification(int $recipientId, string $senderName, string $messageBody, int $conversationId, int $messageId, ?string $attachmentMimeType = null): bool
    {
        // Create deep link URLs
        $appDeepLink = "gekychat://c/{$conversationId}";
        $universalLink = "https://chat.gekychat.com/c/{$conversationId}";
        
        // Truncate message body for notification (max 100 chars)
        $notificationBody = mb_strlen($messageBody) > 100 
            ? mb_substr($messageBody, 0, 100) . '...' 
            : $messageBody;
        
        $data = [
            'type' => 'new_message',
            'conversation_id' => (string) $conversationId,
            'message_id' => (string) $messageId,
            'sender_name' => $senderName,
            'body' => $messageBody,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'deep_link' => $appDeepLink,
            'universal_link' => $universalLink,
            'web_link' => $universalLink, // For desktop/web
        ];
        if ($attachmentMimeType !== null) {
            $data['mime_type'] = $attachmentMimeType;
            $data['attachment_type'] = str_starts_with($attachmentMimeType, 'image/') ? 'image'
                : (str_starts_with($attachmentMimeType, 'video/') ? 'video'
                : (str_starts_with($attachmentMimeType, 'audio/') ? 'audio' : 'document'));
        }
        return $this->sendToUser($recipientId, [
            'title' => $senderName,
            'body' => $notificationBody,
        ], $data);
    }

    /**
     * Send new status notification
     */
    public function sendStatusNotification(int $recipientId, string $userName, int $statusId): bool
    {
        return $this->sendToUser($recipientId, [
            'title' => $userName,
            'body' => 'Posted a new status',
        ], [
            'type' => 'status',
            'user_id' => (string) $recipientId,
            'status_id' => (string) $statusId,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);
    }

    /**
     * Send reaction notification
     */
    public function sendReactionNotification(int $recipientId, string $userName, string $emoji, int $messageId): bool
    {
        return $this->sendToUser($recipientId, [
            'title' => $userName,
            'body' => "Reacted {$emoji} to your message",
        ], [
            'type' => 'reaction',
            'message_id' => (string) $messageId,
            'emoji' => $emoji,
            'user_name' => $userName,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ]);
    }
}

