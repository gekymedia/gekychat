<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Client app version requirements (mobile + desktop)
    |--------------------------------------------------------------------------
    |
    | Override per platform via .env or system_settings (group: app_versions).
    | Version format: semver with optional build, e.g. 1.0.0+89
    |
    */

    'platforms' => [
        'android' => [
            'latest_version' => env('APP_VERSION_ANDROID_LATEST', '1.0.1+102'),
            'min_version' => env('APP_VERSION_ANDROID_MIN', '1.0.0+1'),
            'download_url' => env('APP_VERSION_ANDROID_URL', ''),
        ],
        'ios' => [
            'latest_version' => env('APP_VERSION_IOS_LATEST', '1.0.0+89'),
            'min_version' => env('APP_VERSION_IOS_MIN', '1.0.0+1'),
            'download_url' => env('APP_VERSION_IOS_URL', ''),
        ],
        'windows' => [
            'latest_version' => env('APP_VERSION_WINDOWS_LATEST', '1.0.0+1'),
            'min_version' => env('APP_VERSION_WINDOWS_MIN', '1.0.0+1'),
            'download_url' => env('APP_VERSION_WINDOWS_URL', 'https://gekychat.com/downloads/GekyChat-Setup-1.0.0.exe'),
        ],
        'macos' => [
            'latest_version' => env('APP_VERSION_MACOS_LATEST', '1.0.0+1'),
            'min_version' => env('APP_VERSION_MACOS_MIN', '1.0.0+1'),
            'download_url' => env('APP_VERSION_MACOS_URL', ''),
        ],
        'linux' => [
            'latest_version' => env('APP_VERSION_LINUX_LATEST', '1.0.0+1'),
            'min_version' => env('APP_VERSION_LINUX_MIN', '1.0.0+1'),
            'download_url' => env('APP_VERSION_LINUX_URL', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Android Play In-App Updates (Google Play only)
    |--------------------------------------------------------------------------
    */

    'android_in_app_update' => [
        'enabled' => (bool) env('APP_VERSION_ANDROID_IN_APP_UPDATE', false),
        'priority' => env('APP_VERSION_ANDROID_IN_APP_UPDATE_PRIORITY', 'flexible'),
    ],

];
