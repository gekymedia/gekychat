<?php

namespace App\Services\Sika;

use App\Exceptions\Sika\PbgApiException;
use App\Exceptions\Sika\PbgInsufficientFundsException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PriorityBankService
{
    private string $baseUrl;
    private string $apiKey;
    private string $apiSecret;
    private int $timeout;

    public function __construct()
    {
        $this->baseUrl = config('sika.pbg.base_url') ?? 'https://api.prioritybank.gh/v1';
        $this->apiKey = config('sika.pbg.api_key') ?? '';
        $this->apiSecret = config('sika.pbg.api_secret') ?? '';
        $this->timeout = (int) (config('sika.pbg.timeout') ?? 30);
    }

    /**
     * Get user's PBG wallet balance
     */
    public function getWalletBalance(int $userId): array
    {
        $response = $this->makeRequest('GET', "/wallets/user/{$userId}/balance");

        return [
            'balance' => $response['balance'] ?? 0,
            'currency' => $response['currency'] ?? 'GHS',
            'available_balance' => $response['available_balance'] ?? $response['balance'] ?? 0,
        ];
    }

    /**
     * Debit user's PBG wallet for coin purchase
     * 
     * @param int $userId The user ID in PBG system
     * @param float $amount Amount in GHS to debit
     * @param string $idempotencyKey Unique key to prevent duplicate transactions
     * @param array $metadata Additional transaction metadata
     * @return array Transaction result with reference ID
     * @throws PbgApiException
     * @throws PbgInsufficientFundsException
     */
    public function debitWallet(
        int $userId,
        float $amount,
        string $idempotencyKey,
        array $metadata = []
    ): array {
        $payload = [
            'user_id' => $userId,
            'amount' => round($amount, 2),
            'currency' => 'GHS',
            'type' => 'SIKA_COIN_PURCHASE',
            'idempotency_key' => $idempotencyKey,
            'description' => $metadata['description'] ?? 'Priority Sika Coins Purchase',
            'metadata' => array_merge($metadata, [
                'source' => 'gekychat',
                'transaction_type' => 'coin_purchase',
            ]),
        ];

        try {
            $response = $this->makeRequest('POST', '/wallets/debit', $payload);

            return [
                'success' => true,
                'transaction_id' => $response['transaction_id'],
                'reference' => $response['reference'] ?? $response['transaction_id'],
                'amount' => $response['amount'],
                'new_balance' => $response['new_balance'] ?? null,
                'timestamp' => $response['timestamp'] ?? now()->toIso8601String(),
            ];
        } catch (PbgApiException $e) {
            if ($e->getCode() === 402 || str_contains(strtolower($e->getMessage()), 'insufficient')) {
                throw new PbgInsufficientFundsException(
                    'Insufficient funds in Priority Bank wallet',
                    402,
                    $e
                );
            }
            throw $e;
        }
    }

    /**
     * Credit user's PBG wallet (for cashout)
     * 
     * @param int $userId The user ID in PBG system
     * @param float $amount Amount in GHS to credit
     * @param string $idempotencyKey Unique key to prevent duplicate transactions
     * @param array $metadata Additional transaction metadata
     * @return array Transaction result with reference ID
     * @throws PbgApiException
     */
    public function creditWallet(
        int $userId,
        float $amount,
        string $idempotencyKey,
        array $metadata = []
    ): array {
        $payload = [
            'user_id' => $userId,
            'amount' => round($amount, 2),
            'currency' => 'GHS',
            'type' => 'SIKA_COIN_CASHOUT',
            'idempotency_key' => $idempotencyKey,
            'description' => $metadata['description'] ?? 'Priority Sika Coins Cashout',
            'metadata' => array_merge($metadata, [
                'source' => 'gekychat',
                'transaction_type' => 'coin_cashout',
            ]),
        ];

        $response = $this->makeRequest('POST', '/wallets/credit', $payload);

        return [
            'success' => true,
            'transaction_id' => $response['transaction_id'],
            'reference' => $response['reference'] ?? $response['transaction_id'],
            'amount' => $response['amount'],
            'new_balance' => $response['new_balance'] ?? null,
            'timestamp' => $response['timestamp'] ?? now()->toIso8601String(),
        ];
    }

    /**
     * Verify a transaction status
     */
    public function verifyTransaction(string $transactionId): array
    {
        $response = $this->makeRequest('GET', "/transactions/{$transactionId}");

        return [
            'transaction_id' => $response['transaction_id'],
            'status' => $response['status'],
            'amount' => $response['amount'],
            'type' => $response['type'],
            'created_at' => $response['created_at'],
        ];
    }

    /**
     * Reverse a transaction (for refunds)
     */
    public function reverseTransaction(
        string $originalTransactionId,
        string $idempotencyKey,
        string $reason = ''
    ): array {
        $payload = [
            'original_transaction_id' => $originalTransactionId,
            'idempotency_key' => $idempotencyKey,
            'reason' => $reason,
        ];

        $response = $this->makeRequest('POST', '/transactions/reverse', $payload);

        return [
            'success' => true,
            'reversal_transaction_id' => $response['transaction_id'],
            'original_transaction_id' => $originalTransactionId,
            'status' => $response['status'],
        ];
    }

    /**
     * Check if user has sufficient balance
     */
    public function hasSufficientBalance(int $userId, float $amount): bool
    {
        try {
            $balance = $this->getWalletBalance($userId);
            return ($balance['available_balance'] ?? 0) >= $amount;
        } catch (\Exception $e) {
            Log::error('PBG balance check failed', [
                'user_id' => $userId,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Make HTTP request to PBG API
     */
    private function makeRequest(string $method, string $endpoint, array $data = []): array
    {
        $url = rtrim($this->baseUrl, '/') . '/' . ltrim($endpoint, '/');
        $timestamp = now()->toIso8601String();
        
        $headers = [
            'X-API-Key' => $this->apiKey,
            'X-Timestamp' => $timestamp,
            'X-Signature' => $this->generateSignature($method, $endpoint, $data, $timestamp),
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];

        Log::debug('PBG API Request', [
            'method' => $method,
            'url' => $url,
            'data' => $this->sanitizeLogData($data),
        ]);

        try {
            $request = Http::withHeaders($headers)
                ->timeout($this->timeout);

            /** @var Response $response */
            $response = match (strtoupper($method)) {
                'GET' => $request->get($url, $data),
                'POST' => $request->post($url, $data),
                'PUT' => $request->put($url, $data),
                'DELETE' => $request->delete($url, $data),
                default => throw new PbgApiException("Unsupported HTTP method: {$method}"),
            };

            Log::debug('PBG API Response', [
                'status' => $response->status(),
                'body' => $this->sanitizeLogData($response->json() ?? []),
            ]);

            if ($response->failed()) {
                $errorBody = $response->json();
                $errorMessage = $errorBody['message'] ?? $errorBody['error'] ?? 'Unknown PBG API error';
                
                throw new PbgApiException(
                    $errorMessage,
                    $response->status(),
                    null,
                    $errorBody
                );
            }

            return $response->json() ?? [];

        } catch (PbgApiException $e) {
            throw $e;
        } catch (\Exception $e) {
            Log::error('PBG API Request Failed', [
                'method' => $method,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);
            
            throw new PbgApiException(
                'Failed to communicate with Priority Bank: ' . $e->getMessage(),
                500,
                $e
            );
        }
    }

    /**
     * Generate HMAC signature for request authentication
     */
    private function generateSignature(
        string $method,
        string $endpoint,
        array $data,
        string $timestamp
    ): string {
        $payload = strtoupper($method) . $endpoint . $timestamp;
        
        if (!empty($data)) {
            $payload .= json_encode($data);
        }

        return hash_hmac('sha256', $payload, $this->apiSecret);
    }

    /**
     * Sanitize sensitive data for logging
     */
    private function sanitizeLogData(array $data): array
    {
        $sensitiveKeys = ['api_key', 'api_secret', 'password', 'token', 'signature'];
        
        foreach ($data as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $data[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizeLogData($value);
            }
        }

        return $data;
    }

    /**
     * Health check for PBG API
     */
    public function healthCheck(): bool
    {
        try {
            $response = $this->makeRequest('GET', '/health');
            return ($response['status'] ?? '') === 'ok';
        } catch (\Exception $e) {
            Log::warning('PBG health check failed', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
