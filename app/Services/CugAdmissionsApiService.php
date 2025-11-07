<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class CugAdmissionsApiService
{
    private $baseUrl;
    private $apiKey;
    private $timeout;

    public function __construct()
    {
        $this->baseUrl = config('services.cug_admissions.base_url', 'https://cug.prioritysolutionsagency.com/api');
        $this->apiKey = config('services.cug_admissions.api_key');
        $this->timeout = config('services.cug_admissions.timeout', 30);
    }

    /**
     * Extract applicant information from text/document
     */
    public function extractApplicantInfo(string $text, ?string $documentType = null, ?string $source = 'gekychat_bot'): array
    {
        // Validate input
        if (empty(trim($text))) {
            return [
                'success' => false,
                'message' => 'Text cannot be empty'
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/ai/extract', [
                    'text' => $text,
                    'document_type' => $documentType,
                    'source' => $source,
                    'timestamp' => now()->toISOString()
                ]);

            return $this->handleResponse($response, 'Information extracted successfully');

        } catch (\Exception $e) {
            Log::error('CUG Admissions API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Service temporarily unavailable. Please try again later.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Process document file through CUG AI system
     */
    public function processDocument(string $filePath, string $originalName, ?string $userId = null): array
    {
        // Validate file exists
        if (!Storage::exists($filePath)) {
            return [
                'success' => false,
                'message' => 'File not found: ' . $filePath
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->attach('document', file_get_contents(Storage::path($filePath)), $originalName)
                ->post($this->baseUrl . '/ai/process-document', [
                    'source' => 'gekychat_bot',
                    'user_id' => $userId ?? 'unknown',
                    'original_name' => $originalName,
                    'timestamp' => now()->toISOString()
                ]);

            return $this->handleResponse($response, 'Document processed successfully');

        } catch (\Exception $e) {
            Log::error('CUG Document Processing API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Document processing service unavailable.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get FAQ answers from CUG AI system
     */
    public function getFaqAnswer(string $question, string $context = 'admissions'): array
    {
        $cacheKey = 'cug_faq_' . md5($question . $context);
        
        return Cache::remember($cacheKey, 3600, function () use ($question, $context) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getHeaders())
                    ->post($this->baseUrl . '/ai/faq', [
                        'question' => $question,
                        'context' => $context,
                        'source' => 'gekychat_bot',
                        'timestamp' => now()->toISOString()
                    ]);

                return $this->handleResponse($response, 'FAQ answer retrieved');

            } catch (\Exception $e) {
                Log::error('CUG FAQ API Error: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'FAQ service temporarily unavailable.',
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Submit form data to CUG admissions system
     */
    public function submitApplication(array $formData, ?string $userId = null): array
    {
        // Basic validation
        if (empty($formData)) {
            return [
                'success' => false,
                'message' => 'Form data cannot be empty'
            ];
        }

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->post($this->baseUrl . '/applications/submit', [
                    'applicant_data' => $formData,
                    'submitted_via' => 'gekychat_bot',
                    'user_id' => $userId,
                    'timestamp' => now()->toISOString(),
                    'metadata' => [
                        'ip_address' => request()->ip(),
                        'user_agent' => request()->userAgent()
                    ]
                ]);

            return $this->handleResponse($response, 'Application submitted successfully');

        } catch (\Exception $e) {
            Log::error('CUG Application Submission API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Application submission service unavailable.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Check application status
     */
    public function checkApplicationStatus(string $applicationId): array
    {
        if (empty($applicationId)) {
            return [
                'success' => false,
                'message' => 'Application ID cannot be empty'
            ];
        }

        $cacheKey = 'cug_status_' . md5($applicationId);
        
        return Cache::remember($cacheKey, 300, function () use ($applicationId) { // Cache for 5 minutes
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getHeaders())
                    ->get($this->baseUrl . '/applications/' . urlencode($applicationId) . '/status');

                return $this->handleResponse($response, 'Status retrieved successfully');

            } catch (\Exception $e) {
                Log::error('CUG Status Check API Error: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Status check service unavailable.',
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Get programmes list from CUG system
     */
    public function getProgrammes(string $type = 'all', ?string $category = null): array
    {
        $cacheKey = 'cug_programmes_' . md5($type . $category);
        
        return Cache::remember($cacheKey, 86400, function () use ($type, $category) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getHeaders())
                    ->get($this->baseUrl . '/programmes', [
                        'type' => $type,
                        'category' => $category,
                        'timestamp' => now()->toISOString()
                    ]);

                return $this->handleResponse($response, 'Programmes retrieved successfully');

            } catch (\Exception $e) {
                Log::error('CUG Programmes API Error: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Programmes service unavailable.',
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Get programme details by ID
     */
    public function getProgrammeDetails(string $programmeId): array
    {
        $cacheKey = 'cug_programme_' . md5($programmeId);
        
        return Cache::remember($cacheKey, 86400, function () use ($programmeId) {
            try {
                $response = Http::timeout($this->timeout)
                    ->withHeaders($this->getHeaders())
                    ->get($this->baseUrl . '/programmes/' . urlencode($programmeId));

                return $this->handleResponse($response, 'Programme details retrieved successfully');

            } catch (\Exception $e) {
                Log::error('CUG Programme Details API Error: ' . $e->getMessage());
                return [
                    'success' => false,
                    'message' => 'Programme details service unavailable.',
                    'error' => $e->getMessage()
                ];
            }
        });
    }

    /**
     * Health check for API connectivity
     */
    public function healthCheck(): array
    {
        try {
            $startTime = microtime(true);
            
            $response = Http::timeout(10)
                ->withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/health');

            $responseTime = round((microtime(true) - $startTime) * 1000, 2);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'status' => 'healthy',
                    'response_time_ms' => $responseTime,
                    'timestamp' => now()->toISOString()
                ];
            }

            return [
                'success' => false,
                'status' => 'unhealthy',
                'message' => 'API returned error status: ' . $response->status(),
                'response_time_ms' => $responseTime,
                'timestamp' => now()->toISOString()
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 'unreachable',
                'message' => 'API is unreachable',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString()
            ];
        }
    }

    /**
     * Get API usage statistics (if available)
     */
    public function getUsageStats(): array
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getHeaders())
                ->get($this->baseUrl . '/stats/usage');

            return $this->handleResponse($response, 'Usage stats retrieved successfully');

        } catch (\Exception $e) {
            Log::error('CUG Usage Stats API Error: ' . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Usage stats service unavailable.',
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Common headers for all requests
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
            'User-Agent' => 'GekyChat-Bot/1.0',
            'X-Request-ID' => uniqid('cug_', true)
        ];
    }

    /**
     * Handle API response consistently
     */
    private function handleResponse($response, string $successMessage): array
    {
        if ($response->successful()) {
            return [
                'success' => true,
                'data' => $response->json(),
                'message' => $successMessage,
                'status_code' => $response->status(),
                'timestamp' => now()->toISOString()
            ];
        }

        // Log detailed error information
        Log::warning('CUG API Error Response', [
            'status' => $response->status(),
            'body' => $response->body(),
            'headers' => $response->headers()
        ]);

        return [
            'success' => false,
            'message' => 'API request failed: ' . $response->body(),
            'status_code' => $response->status(),
            'timestamp' => now()->toISOString()
        ];
    }

    /**
     * Clear cache for specific endpoints
     */
    public function clearCache(?string $key = null): void
    {
        if ($key) {
            Cache::forget($key);
        } else {
            // Clear all CUG-related cache
            Cache::forget('cug_programmes_list');
            Cache::forget('cug_programmes_all');
            // Add more cache keys as needed
        }
    }

    /**
     * Validate API configuration
     */
    public function validateConfig(): array
    {
        $issues = [];

        if (empty($this->baseUrl)) {
            $issues[] = 'Base URL is not configured';
        }

        if (empty($this->apiKey)) {
            $issues[] = 'API key is not configured';
        }

        if (!filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            $issues[] = 'Base URL is not a valid URL';
        }

        return [
            'valid' => empty($issues),
            'issues' => $issues,
            'config' => [
                'base_url' => $this->baseUrl,
                'api_key_set' => !empty($this->apiKey),
                'timeout' => $this->timeout
            ]
        ];
    }
}