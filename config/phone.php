<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ghana mobile prefixes (national format 0XX…)
    |--------------------------------------------------------------------------
    | Only these 3-digit prefixes may receive OTP SMS. Keeps bots/crawlers from
    | burning SMS credits on fake or international numbers.
    | Update when NCA assigns new ranges.
    */
    'ghana_mobile_prefixes' => [
        '020', // Telecel
        '023', // Glo
        '024', // MTN
        '025', // MTN
        '026', // AT
        '027', // AT
        '050', // Telecel
        '053', // MTN
        '054', // MTN
        '055', // MTN
        '056', // AT
        '057', // AT
        '059', // MTN
    ],

    /*
    |--------------------------------------------------------------------------
    | OTP rate limits
    |--------------------------------------------------------------------------
    */
    'otp_hourly_limit_per_phone' => (int) env('OTP_HOURLY_LIMIT_PER_PHONE', 3),
    'otp_hourly_limit_per_ip' => (int) env('OTP_HOURLY_LIMIT_PER_IP', 10),

    /*
    |--------------------------------------------------------------------------
    | Dev / QA test numbers (never SMS — fixed OTP only when explicitly allowed)
    |--------------------------------------------------------------------------
    */
    'allow_test_numbers' => (bool) env('PHONE_ALLOW_TEST_NUMBERS', false),
    'test_numbers' => [
        '1111111111' => '123456',
    ],

];
