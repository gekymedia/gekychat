<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Priority Bank Ghana (PBG) Integration
    |--------------------------------------------------------------------------
    |
    | Configuration for Priority Bank API integration. PBG handles the real
    | money (GHS) transactions while GekyChat manages the virtual Sika Coins.
    |
    */
    'pbg' => [
        'base_url' => env('PBG_API_BASE_URL', 'https://bank.prioritysolutionsagency.com/api'),
        'api_key' => env('PBG_API_KEY'),
        'api_secret' => env('PBG_API_SECRET'),
        'timeout' => env('PBG_API_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Internal Webhook Security
    |--------------------------------------------------------------------------
    |
    | Secret key for validating webhooks between PBG and GekyChat.
    |
    */
    'webhook_secret' => env('SIKA_INTERNAL_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Feature Flags
    |--------------------------------------------------------------------------
    |
    | Control which features are enabled. Cashout and merchant payments
    | are disabled by default and should be enabled when ready.
    |
    */
    'features' => [
        'cashout_enabled' => env('SIKA_FEATURE_CASHOUT_ENABLED', false),
        'merchant_pay_enabled' => env('SIKA_FEATURE_MERCHANT_PAY_ENABLED', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Transaction Limits
    |--------------------------------------------------------------------------
    |
    | Default limits for various transaction types.
    |
    */
    'limits' => [
        'min_transfer' => env('SIKA_MIN_TRANSFER', 1),
        'max_transfer' => env('SIKA_MAX_TRANSFER', 100000000),
        'min_gift' => env('SIKA_MIN_GIFT', 1),
        'max_gift' => env('SIKA_MAX_GIFT', 10000000),
    ],

    /*
    |--------------------------------------------------------------------------
    | Display Settings
    |--------------------------------------------------------------------------
    |
    | Settings for how coins are displayed in the UI.
    |
    */
    'display' => [
        'currency_symbol' => '🪙',
        'currency_name' => 'Sika Coins',
        'currency_short' => 'SC',
    ],

    /*
    |--------------------------------------------------------------------------
    | Anti-Fraud Settings
    |--------------------------------------------------------------------------
    |
    | Settings to help prevent fraudulent activity.
    |
    */
    'anti_fraud' => [
        'max_daily_purchases' => env('SIKA_MAX_DAILY_PURCHASES', 10),
        'max_daily_transfers' => env('SIKA_MAX_DAILY_TRANSFERS', 50),
        'suspicious_amount_threshold' => env('SIKA_SUSPICIOUS_AMOUNT', 10000000),
        'new_account_restriction_days' => env('SIKA_NEW_ACCOUNT_RESTRICTION_DAYS', 0),
    ],
];
