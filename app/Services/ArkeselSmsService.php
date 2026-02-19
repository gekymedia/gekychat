<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ArkeselSmsService implements SmsServiceInterface
{
    protected $apiKey;
    protected $senderId;
    protected $baseUrl;

    /** HTTP timeout in seconds (avoid blocking; Arkesel typically responds in <2s) */
    protected int $timeout = 15;

    /** Connect timeout in seconds */
    protected int $connectTimeout = 5;

    public function __construct()
    {
        $this->apiKey   = trim((string) config('arkesel.api_key'));
        $this->senderId = (string) config('arkesel.sender_id');
        $this->baseUrl  = (string) config('arkesel.endpoint');
        $this->timeout  = (int) config('arkesel.timeout', 15);
    }

    public function sendSms(string $phoneNumber, string $message): array
    {
        if ($this->apiKey === '') {
            Log::error('Arkesel SMS: missing ARKESEL_API_KEY');
            return ['success' => false, 'error' => 'SMS service not configured'];
        }

        $recipient = $this->formatPhoneNumber($phoneNumber);
        $payload = [
            'sender'     => $this->senderId,
            'message'    => $message,
            'recipients' => [$recipient],
        ];

        $attempt = 1;
        $maxAttempts = 2;

        while ($attempt <= $maxAttempts) {
            $start = microtime(true);
            try {
                $response = Http::withHeaders([
                    'api-key' => $this->apiKey,
                ])
                    ->timeout($this->timeout)
                    ->connectTimeout($this->connectTimeout)
                    ->post($this->baseUrl, $payload);

                $durationMs = round((microtime(true) - $start) * 1000);

                if (!$response->successful()) {
                    Log::warning('Arkesel SMS failed', [
                        'attempt' => $attempt,
                        'status' => $response->status(),
                        'body' => $response->body(),
                        'duration_ms' => $durationMs,
                        'recipient' => $recipient,
                    ]);
                    if ($attempt < $maxAttempts) {
                        $attempt++;
                        usleep(100000); // 100ms before retry
                        continue;
                    }
                }

                $result = $response->json() ?? [];

                if ($response->successful() && (($result['status'] ?? '') === 'success')) {
                    Log::info('Arkesel SMS sent', [
                        'recipient' => $recipient,
                        'duration_ms' => $durationMs,
                        'message_id' => $result['data']['message_id'] ?? null,
                    ]);
                    return [
                        'success'    => true,
                        'balance'    => $result['data']['credit_balance'] ?? null,
                        'message_id' => $result['data']['message_id'] ?? null,
                    ];
                }

                $error = $result['message'] ?? ($result['error'] ?? 'Failed to send SMS');
                Log::warning('Arkesel SMS rejected', [
                    'attempt' => $attempt,
                    'error' => $error,
                    'body' => $response->body(),
                    'duration_ms' => $durationMs,
                ]);
                if ($attempt < $maxAttempts) {
                    $attempt++;
                    usleep(100000);
                    continue;
                }
                return ['success' => false, 'error' => $error];

            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                $durationMs = isset($start) ? round((microtime(true) - $start) * 1000) : 0;
                Log::error('Arkesel SMS connection error', [
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                    'duration_ms' => $durationMs,
                ]);
                if ($attempt < $maxAttempts) {
                    $attempt++;
                    usleep(200000); // 200ms before retry
                    continue;
                }
                return ['success' => false, 'error' => 'SMS service temporarily unavailable. Please try again.'];
            } catch (\Throwable $e) {
                Log::error('Arkesel SMS Exception: ' . $e->getMessage(), [
                    'attempt' => $attempt,
                    'trace' => $e->getTraceAsString(),
                ]);
                return ['success' => false, 'error' => 'Service unavailable. Please try again later.'];
            }
        }

        return ['success' => false, 'error' => 'Failed to send SMS after retries.'];
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