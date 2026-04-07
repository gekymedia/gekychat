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
    /**
     * Resolve credentials path: if absolute, use as-is; otherwise relative to project base path.
     */
    protected function resolveCredentialsPath(): ?string
    {
        if (!$this->credentialsPath || $this->credentialsPath === '') {
            return null;
        }
        $path = $this->credentialsPath;
        // Absolute path (Unix or Windows) – use as-is
        if ($path[0] === '/' || (strlen($path) > 1 && $path[1] === ':')) {
            return $path;
        }
        return base_path($path);
    }

    protected function getAccessToken(): ?string
    {
        try {
            $resolvedPath = $this->resolveCredentialsPath();
            if (!$resolvedPath || !file_exists($resolvedPath)) {
                Log::error('FCM credentials file not found', ['path' => $this->credentialsPath, 'resolved' => $resolvedPath]);
                return null;
            }

            $credentials = json_decode(file_get_contents($resolvedPath), true);
            
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
     * Send data-only payload to a token (no notification block).
     * Use for chat/group messages so the app shows one per-chat notification (like calls).
     * Android: collapse_key groups by conversation so the system can replace when app is in background.
     */
    public function sendDataOnlyToToken(string $token, array $data, ?string $collapseKey = null): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        $dataString = [];
        foreach ($data as $k => $v) {
            $dataString[$k] = (string) $v;
        }
        $payload = [
            'message' => [
                'token' => $token,
                'data' => $dataString,
                'android' => [
                    'priority' => 'high',
                    ...($collapseKey !== null ? ['collapse_key' => $collapseKey] : []),
                ],
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'background',
                    ],
                    'payload' => [
                        'aps' => [
                            'content-available' => 1,
                        ],
                    ],
                ],
            ],
        ];
        return $this->executeSend($token, $payload);
    }

    /**
     * Send data + visible notification payload to iOS tokens.
     * iOS may drop data-only pushes when app is terminated, so we include alert.
     */
    public function sendAlertDataToToken(string $token, array $data): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }

        $dataString = [];
        foreach ($data as $k => $v) {
            $dataString[$k] = (string) $v;
        }

        $title = (string) ($data['title'] ?? 'New message');
        $body = (string) ($data['message'] ?? ($data['body'] ?? 'You have a new message'));

        $payload = [
            'message' => [
                'token' => $token,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'data' => $dataString,
                'apns' => [
                    'headers' => [
                        'apns-priority' => '10',
                        'apns-push-type' => 'alert',
                    ],
                    'payload' => [
                        'aps' => [
                            'alert' => [
                                'title' => $title,
                                'body' => $body,
                            ],
                            // Required for iOS actionable notifications (Reply / Mark as read).
                            'category' => 'MESSAGE_CATEGORY',
                            'sound' => 'default',
                            'badge' => 1,
                        ],
                    ],
                ],
            ],
        ];

        return $this->executeSend($token, $payload);
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

        return $this->executeSend($token, $payload);
    }

    /**
     * Execute FCM send and handle token errors
     */
    protected function executeSend(string $token, array $payload): bool
    {
        $accessToken = $this->getAccessToken();
        if (!$accessToken) {
            return false;
        }
        $payload['message']['token'] = $token;
        if (!isset($payload['message']['data'])) {
            $payload['message']['data'] = [];
        }
        $data = $payload['message']['data'];
        $payload['message']['data'] = array_map(function ($v) {
            return (string) $v;
        }, $data);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $accessToken,
                'Content-Type' => 'application/json',
            ])->post($this->fcmUrl, $payload);

            if ($response->successful()) {
                return true;
            }

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
     * Send data-only FCM to all tokens for a user (no notification block).
     * Ensures one per-chat notification is built by the app (like call notifications).
     * @param string|null $collapseKey Android collapse key (e.g. gekychat_conv_123) so FCM replaces by conversation
     */
    public function sendDataOnlyToUser(int $userId, array $data, ?string $collapseKey = null): bool
    {
        $devices = DeviceToken::where('user_id', $userId)
            ->get(['token', 'device_type']);
        if ($devices->isEmpty()) {
            return false;
        }

        $success = false;
        foreach ($devices as $device) {
            $token = (string) $device->token;
            $deviceType = strtolower((string) ($device->device_type ?? ''));

            // iOS: use visible alert payload so notifications arrive when app is backgrounded/killed.
            $sent = $deviceType === 'ios'
                ? $this->sendAlertDataToToken($token, $data)
                : $this->sendDataOnlyToToken($token, $data, $collapseKey);

            if ($sent) {
                $success = true;
            }
        }
        return $success;
    }

    /**
     * Send new message notification
     * @param string|null $attachmentMimeType Optional MIME type for media label (e.g. image/jpeg → "📷 Photo")
     * @param string|null $senderAvatarUrl Optional full URL for sender avatar (rich notification)
     */
    public function sendMessageNotification(int $recipientId, string $senderName, string $messageBody, int $conversationId, int $messageId, ?string $attachmentMimeType = null, ?string $senderAvatarUrl = null): bool
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
            'title' => $senderName,
            'message' => $notificationBody,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
            'deep_link' => $appDeepLink,
            'universal_link' => $universalLink,
            'web_link' => $universalLink,
        ];
        if ($senderAvatarUrl !== null && $senderAvatarUrl !== '') {
            $data['sender_avatar'] = $senderAvatarUrl;
        }
        if ($attachmentMimeType !== null) {
            $data['mime_type'] = $attachmentMimeType;
            $data['attachment_type'] = str_starts_with($attachmentMimeType, 'image/') ? 'image'
                : (str_starts_with($attachmentMimeType, 'video/') ? 'video'
                : (str_starts_with($attachmentMimeType, 'audio/') ? 'audio' : 'document'));
        }
        // collapse_key so Android groups/replaces by conversation when app is in background
        $collapseKey = 'gekychat_conv_' . $conversationId;
        return $this->sendDataOnlyToUser($recipientId, $data, $collapseKey);
    }

    /**
     * Send missed-call notification (separate from message notifications on mobile).
     * Mobile app shows these in the "Missed calls" group.
     *
     * @param int $recipientId User to notify (the one who missed the call)
     * @param string $callerName Name of the person who called
     * @param int $conversationId Conversation ID (for DM)
     * @param int $messageId Message ID of the call message
     * @param string|null $callerAvatarUrl Optional avatar URL
     * @param int|null $groupId Group ID if group call
     * @param string|null $groupName Group name if group call
     */
    public function sendMissedCallNotification(
        int $recipientId,
        string $callerName,
        int $conversationId,
        int $messageId,
        ?string $callerAvatarUrl = null,
        ?int $groupId = null,
        ?string $groupName = null
    ): bool {
        $title = $groupId && $groupName
            ? $groupName
            : $callerName;
        $body = 'Missed call from ' . $callerName;

        $data = [
            'type' => 'missed_call',
            'call_missed' => 'true',
            'payload_type' => 'call', // FCM v1 rejects key "message_type"
            'conversation_id' => (string) $conversationId,
            'message_id' => (string) $messageId,
            'sender_name' => $callerName,
            'body' => $body,
            'title' => $title,
            'click_action' => 'FLUTTER_NOTIFICATION_CLICK',
        ];
        if ($callerAvatarUrl !== null && $callerAvatarUrl !== '') {
            $data['sender_avatar'] = $callerAvatarUrl;
        }
        if ($groupId !== null) {
            $data['group_id'] = (string) $groupId;
            if ($groupName !== null) {
                $data['group_name'] = $groupName;
            }
        }
        $collapseKey = 'gekychat_calls';
        return $this->sendDataOnlyToUser($recipientId, $data, $collapseKey);
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

