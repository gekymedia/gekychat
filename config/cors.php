<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => ['http://localhost:5173', 'http://127.0.0.1:5173'], // your web dev host(s)
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'Authorization', 'Accept', 'Origin'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false, // bearer tokens do NOT need cookies
];
