<?php

namespace App\Http\Responses;

/**
 * ✅ MODERN: Structured error responses with error codes
 * Consistent error format for better client-side error handling
 * WhatsApp/Telegram-style error responses
 */
class ErrorResponse
{
    /**
     * Error codes for different scenarios
     */
    public const ERROR_VALIDATION = 'VALIDATION_ERROR';
    public const ERROR_UNAUTHORIZED = 'UNAUTHORIZED';
    public const ERROR_FORBIDDEN = 'FORBIDDEN';
    public const ERROR_NOT_FOUND = 'NOT_FOUND';
    public const ERROR_CONFLICT = 'CONFLICT';
    public const ERROR_RATE_LIMIT = 'RATE_LIMIT_EXCEEDED';
    public const ERROR_SERVER = 'SERVER_ERROR';
    public const ERROR_SERVICE_UNAVAILABLE = 'SERVICE_UNAVAILABLE';
    public const ERROR_INVALID_REQUEST = 'INVALID_REQUEST';
    
    /**
     * Create a structured error response
     */
    public static function create(
        string $code,
        string $message,
        ?array $details = null,
        int $httpStatus = 400
    ): \Illuminate\Http\JsonResponse {
        $response = [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
            ],
        ];
        
        if ($details !== null) {
            $response['error']['details'] = $details;
        }
        
        // Add timestamp for debugging
        $response['error']['timestamp'] = now()->toIso8601String();
        
        return response()->json($response, $httpStatus);
    }
    
    /**
     * Validation error response
     */
    public static function validation(array $errors): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_VALIDATION,
            'Validation failed',
            ['fields' => $errors],
            422
        );
    }
    
    /**
     * Unauthorized error response
     */
    public static function unauthorized(string $message = 'Unauthorized'): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_UNAUTHORIZED,
            $message,
            null,
            401
        );
    }
    
    /**
     * Forbidden error response
     */
    public static function forbidden(string $message = 'Forbidden'): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_FORBIDDEN,
            $message,
            null,
            403
        );
    }
    
    /**
     * Not found error response
     */
    public static function notFound(string $resource = 'Resource'): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_NOT_FOUND,
            "{$resource} not found",
            null,
            404
        );
    }
    
    /**
     * Conflict error response (for optimistic concurrency control)
     */
    public static function conflict(string $message = 'Resource conflict'): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_CONFLICT,
            $message,
            null,
            409
        );
    }
    
    /**
     * Rate limit error response
     */
    public static function rateLimit(int $retryAfter = 60): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_RATE_LIMIT,
            'Too many requests',
            ['retry_after_seconds' => $retryAfter],
            429
        );
    }
    
    /**
     * Server error response
     */
    public static function serverError(string $message = 'Internal server error'): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_SERVER,
            $message,
            null,
            500
        );
    }
    
    /**
     * Service unavailable error response
     */
    public static function serviceUnavailable(string $message = 'Service temporarily unavailable'): \Illuminate\Http\JsonResponse
    {
        return self::create(
            self::ERROR_SERVICE_UNAVAILABLE,
            $message,
            null,
            503
        );
    }
}
