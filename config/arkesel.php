<?php

return [
    // Arkesel v2 credentials
    'api_key'  => env('ARKESEL_API_KEY'),                 // the only key we’ll use
    'sender_id'=> env('ARKESEL_SENDER_ID', 'GEKYCHAT'),

    // Correct v2 endpoint (don’t use the old /sms/api)
    'endpoint' => env('ARKESEL_SMS_ENDPOINT', 'https://sms.arkesel.com/api/v2/sms/send'),
];
