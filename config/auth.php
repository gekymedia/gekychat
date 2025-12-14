<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    */
    'guards' => [

        /*
         * Web UI (sessions)
         */
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],

        /*
         * Mobile / Web App API (Sanctum)
         * Used by Flutter, Web SPA, etc.
         */
        'sanctum' => [
            'driver' => 'sanctum',
            'provider' => 'users',
        ],

        /*
         * Platform / Integration API
         * Used by external systems, bots, partners
         */
        'api-client' => [
            'driver' => 'token',
            'provider' => 'api_clients',
            'hash' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    */
    'providers' => [

        /*
         * Human users (mobile + web)
         */
        'users' => [
            'driver' => 'eloquent',
            'model' => App\Models\User::class,
        ],

        /*
         * External API clients (systems, bots, integrations)
         */
        'api_clients' => [
            'driver' => 'eloquent',
            'model' => App\Models\ApiClient::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    */
    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    */
    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),
];
