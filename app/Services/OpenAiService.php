<?php

namespace App\Services;

use OpenAI;
use OpenAI\Exceptions\RateLimitException;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Facades\Log;
use Throwable;

class OpenAiService
{
    /** @var \OpenAI\Client */
    private $client;

    // Retry/timeout configuration
    private const MAX_ATTEMPTS  = 3;
    private const INITIAL_DELAY = 0.35;
    private const MAX_DELAY     = 2.0;
    private const JITTER_MS     = 200;
    private const TIME_BUDGET   = 18.0;

    public function __construct()
    {
        $apiKey = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        
        if (empty($apiKey)) {
            Log::warning('OpenAI API key not configured');
            $this->client = null;
            return;
        }

        // Build client with tight HTTP timeouts
        $http = new GuzzleClient([
            'timeout'         => 10.0,
            'connect_timeout' => 5.0,
        ]);

        $this->client = OpenAI::factory()
            ->withApiKey($apiKey)
            ->withHttpClient($http)
            ->make();
    }

    /**
     * Check if OpenAI API key is configured
     */
    public function hasKey(): bool
    {
        if ($this->client === null) {
            return false;
        }
        $k = config('services.openai.api_key') ?: env('OPENAI_API_KEY');
        return is_string($k) && trim($k) !== '';
    }

    /**
     * Retry wrapper for OpenAI calls with strict time budget
     */
    private function callWithRetry(callable $fn, int $maxAttempts = self::MAX_ATTEMPTS)
    {
        $attempt  = 0;
        $delay    = self::INITIAL_DELAY;
        $deadline = microtime(true) + self::TIME_BUDGET;
        $lastErr  = null;

        while (true) {
            $attempt++;

            try {
                if ($this->client === null) {
                    throw new \Exception('OpenAI client not initialized. Please check OPENAI_API_KEY in your .env file.');
                }
                return $fn();
            } catch (RateLimitException $e) {
                $lastErr = $e;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $retryAfter = null;
                if (method_exists($e, 'response') && $e->response && method_exists($e->response, 'getHeaderLine')) {
                    $h = $e->response->getHeaderLine('Retry-After');
                    if ($h !== '') {
                        $retryAfter = (float) $h;
                    }
                }

                $sleep = $retryAfter ?? ($delay + (random_int(0, self::JITTER_MS) / 1000));
            } catch (Throwable $e) {
                $lastErr = $e;
                if ($attempt >= $maxAttempts) {
                    throw $e;
                }

                $sleep = $delay + (random_int(0, self::JITTER_MS) / 1000);
            }

            $left = $deadline - microtime(true);
            if ($left <= 0.2) {
                throw new \RuntimeException('OpenAiService exceeded retry/timeout budget: ' . ($lastErr?->getMessage() ?? 'unknown error'));
            }

            $sleep = min($sleep, self::MAX_DELAY, max(0.0, $left - 0.05));
            if ($sleep > 0) {
                usleep((int) round($sleep * 1_000_000));
            }

            $delay = min($delay * 2, self::MAX_DELAY);
        }
    }

    /**
     * Generate chat response using OpenAI
     * 
     * @param string $messageText User's message
     * @param array $context Previous conversation messages (optional)
     * @param string $systemPrompt Custom system prompt (optional)
     * @return string|null AI response or null on failure
     */
    public function generateChatResponse(string $messageText, array $context = [], string $systemPrompt = null): ?string
    {
        if (!$this->hasKey()) {
            return null;
        }

        $defaultSystemPrompt = "You are GekyBot, a helpful and friendly virtual assistant for GekyChat. "
            . "You help users with questions, provide information, and assist with various tasks. "
            . "Be conversational, natural, and empathetic. Keep responses concise but helpful. "
            . "If you don't know something, admit it politely and suggest where they can find more information.";

        $systemPrompt = $systemPrompt ?? $defaultSystemPrompt;

        // Build messages array
        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        // Add context messages if provided
        foreach ($context as $ctx) {
            if (isset($ctx['role']) && isset($ctx['content'])) {
                $messages[] = ['role' => $ctx['role'], 'content' => $ctx['content']];
            }
        }

        // Add current user message
        $messages[] = ['role' => 'user', 'content' => $messageText];

        try {
            $response = $this->callWithRetry(function () use ($messages) {
                return $this->client->chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => $messages,
                    'max_tokens' => 500,
                    'temperature' => 0.7,
                ]);
            });

            // Extract response text from OpenAI response object
            $responseText = null;
            if (is_object($response) && isset($response->choices) && is_array($response->choices) && count($response->choices) > 0) {
                $firstChoice = $response->choices[0];
                if (isset($firstChoice->message) && isset($firstChoice->message->content)) {
                    $responseText = $firstChoice->message->content;
                }
            }
            
            if ($responseText) {
                $responseText = trim($responseText);
                // Remove any bot name prefixes
                $responseText = preg_replace('/^(GekyBot|Bot|Assistant):\s*/i', '', $responseText);
                return $responseText;
            }

            return null;
        } catch (Throwable $e) {
            Log::error('OpenAI chat generation error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Generate birthday reminder message
     * 
     * @param string $contactName Name of the contact whose birthday it is
     * @param string $userName Name of the user receiving the reminder
     * @return string Birthday reminder message
     */
    public function generateBirthdayReminder(string $contactName, string $userName): string
    {
        if (!$this->hasKey()) {
            // Fallback message if OpenAI is not configured
            return "🎉 Today is {$contactName}'s birthday! Don't forget to wish them a happy birthday! 🎂";
        }

        $systemPrompt = "You are GekyBot, a friendly virtual assistant. Generate a warm, personal birthday reminder message. "
            . "The message should encourage the user to wish their contact a happy birthday. "
            . "Keep it brief (1-2 sentences), friendly, and include emojis. "
            . "Do not include the contact's name in quotes or brackets.";

        $userPrompt = "Generate a birthday reminder message. Contact name: {$contactName}. User name: {$userName}.";

        try {
            $response = $this->generateChatResponse($userPrompt, [], $systemPrompt);
            
            if ($response) {
                return $response;
            }
        } catch (Throwable $e) {
            Log::error('OpenAI birthday reminder generation error: ' . $e->getMessage());
        }

        // Fallback if OpenAI fails
        return "🎉 Today is {$contactName}'s birthday! Don't forget to wish them a happy birthday! 🎂";
    }
}
