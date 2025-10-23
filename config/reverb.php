<?php

return [
    'default' => env('REVERB_SERVER', 'reverb'),

    'servers' => [
        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 6001), // ← Changed from 8080 to 6001
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST', '127.0.0.1'),
            'options' => [
                'tls' => [],
            ],

            'pulse_ingest_interval'     => env('REVERB_PULSE_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INTERVAL', 15),

            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
            ],
        ],
    ],

    'apps' => [
        'provider' => 'config',
        'apps' => [[
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host'   => env('REVERB_HOST', '127.0.0.1'),
                'port'   => env('REVERB_PORT', 6001), // ← Changed from 8080 to 6001
                'scheme' => env('REVERB_SCHEME', 'http'),
                'useTLS' => env('REVERB_SCHEME', 'http') === 'https',
            ],
            'allowed_origins' => ['*'],
            'ping_interval' => 60,
            'max_message_size' => 10_000,
        ]],
    ],

    'client' => [
        'host'   => env('REVERB_HOST', '127.0.0.1'),
        'port'   => env('REVERB_PORT', 6001), // ← Changed from 8080 to 6001
        'scheme' => env('REVERB_SCHEME', 'http'),
        'tls'    => [],
    ],
];