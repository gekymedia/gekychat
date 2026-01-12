<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
    'google' => [
        'client_id' => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect' => env('GOOGLE_REDIRECT_URI'),
    ],
    'cug_admissions' => [
        'base_url' => env('CUG_ADMISSIONS_BASE_URL'),
        'api_key' => env('CUG_ADMISSIONS_API_KEY'),
        'timeout' => env('CUG_ADMISSIONS_TIMEOUT', 30),
    ],

    'blacktask' => [
        'url' => env('BLACKTASK_URL', 'https://blacktask.com'),
        'api_token' => env('BLACKTASK_API_TOKEN'),
        'timeout' => env('BLACKTASK_TIMEOUT', 30),
    ],

    'fcm' => [
        // V1 API (Recommended)
        'credentials_path' => env('FIREBASE_CREDENTIALS', 'storage/app/firebase/firebase-credentials.json'),
        'project_id' => env('FIREBASE_PROJECT_ID'),
        
        // Legacy API (Deprecated - only use if V1 not available)
        'server_key' => env('FCM_SERVER_KEY'),
    ],
    
    'freesound' => [
        'api_key' => env('FREESOUND_API_KEY'),
        'client_id' => env('FREESOUND_CLIENT_ID'),
        'client_secret' => env('FREESOUND_CLIENT_SECRET'),
        'base_url' => 'https://freesound.org/apiv2',
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
    ],

    /*
    |--------------------------------------------------------------------------
    | WebRTC TURN Server Configuration
    |--------------------------------------------------------------------------
    |
    | PHASE 0: TURN server configuration placeholders for Phase 1 call improvements.
    | TURN servers are required for reliable calls behind NAT/firewall.
    |
    | Options:
    | - Twilio TURN (paid, reliable): https://www.twilio.com/stun-turn
    | - coturn (self-hosted, free): https://github.com/coturn/coturn
    | - LiveKit (full media server): https://livekit.io/
    |
    | TODO (PHASE 0): Configure TURN servers in .env file
    | TODO (PHASE 1): Wire TURN servers into CallManager (web/mobile/desktop)
    */
    /*
    |--------------------------------------------------------------------------
    | LiveKit Configuration (PHASE 2)
    |--------------------------------------------------------------------------
    */
    'livekit' => [
        'url' => env('LIVEKIT_URL', 'ws://localhost:7880'),
        'api_key' => env('LIVEKIT_API_KEY'),
        'api_secret' => env('LIVEKIT_API_SECRET'),
    ],

    'webrtc' => [
        'turn' => [
            'enabled' => env('WEBRTC_TURN_ENABLED', false),
            'urls' => explode(',', env('WEBRTC_TURN_URLS', '')),
            'username' => env('WEBRTC_TURN_USERNAME'),
            'credential' => env('WEBRTC_TURN_CREDENTIAL'),
        ],
        'stun' => [
            // Default STUN servers (always used)
            'urls' => [
                'stun:stun.l.google.com:19302',
                'stun:stun1.l.google.com:19302',
            ],
        ],
    ],

];
