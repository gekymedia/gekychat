<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | Set BROADCAST_DRIVER=reverb to use Reverb, or BROADCAST_DRIVER=pusher
    | to use Pusher. Default is pusher for backward compatibility.
    |
    */

    'default' => env('BROADCAST_DRIVER', 'pusher'),

    'connections' => [

        /*
        |--------------------------------------------------------------------------
        | Reverb Configuration (Self-hosted WebSocket)
        |--------------------------------------------------------------------------
        |
        | Reverb is Laravel's official WebSocket server. It uses the Pusher
        | protocol so mobile/web clients don't need code changes.
        |
        */
        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST', 'chat.gekychat.com'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        /*
        |--------------------------------------------------------------------------
        | Pusher Configuration (Cloud WebSocket - Fallback)
        |--------------------------------------------------------------------------
        |
        | Pusher is a cloud-hosted WebSocket service. Keep this as fallback
        | in case Reverb has issues.
        |
        */
        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
