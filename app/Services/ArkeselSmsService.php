<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArkeselSmsService implements SmsServiceInterface
{
    protected $apiKey;
    protected $senderId;
    protected $baseUrl;

  public function __construct()
{
    $this->apiKey   = trim((string) config('arkesel.api_key'));   // trim guards against stray spaces
    $this->senderId = (string) config('arkesel.sender_id');
    $this->baseUrl  = (string) config('arkesel.endpoint');        // ← use 'endpoint'
}

public function sendSms(string $phoneNumber, string $message): array
{
    if ($this->apiKey === '') {
        Log::error('Arkesel SMS: missing ARKESEL_API_KEY');
        return ['success' => false, 'error' => 'SMS service not configured'];
    }

    try {
        $response = Http::withHeaders([
            'api-key' => $this->apiKey,               // v2 header name
        ])->post($this->baseUrl, [
            'sender'     => $this->senderId,
            'message'    => $message,
            'recipients' => [$this->formatPhoneNumber($phoneNumber)],
        ]);

        if (!$response->successful()) {
            Log::error('Arkesel SMS failed', [
                'status' => $response->status(),
                'body'   => $response->body(),
            ]);
        }

        $result = $response->json() ?? [];

        if ($response->successful() && (($result['status'] ?? '') === 'success')) {
            return [
                'success'    => true,
                'balance'    => $result['data']['credit_balance'] ?? null,
                'message_id' => $result['data']['message_id'] ?? null,
            ];
        }

        $error = $result['message'] ?? ($result['error'] ?? 'Failed to send SMS');
        return ['success' => false, 'error' => $error];

    } catch (\Throwable $e) {
        Log::error('Arkesel SMS Exception: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Service unavailable. Please try again later.'];
    }
}

    public function sendOtp(string $phoneNumber, string $otpCode): array
    {
        $message = "Your OTP code is: {$otpCode}. Valid for 5 minutes. Do not share.";
        return $this->sendSms($phoneNumber, $message);
    }

    protected function formatPhoneNumber(string $phoneNumber): string
    {
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);

        if (strlen($phoneNumber) === 10 && str_starts_with($phoneNumber, '0')) {
            return '233' . substr($phoneNumber, 1);
        }

        if (str_starts_with($phoneNumber, '+')) {
            return substr($phoneNumber, 1);
        }

        return $phoneNumber;
    }
}