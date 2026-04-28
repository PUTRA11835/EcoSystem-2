<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    // CORS_ALLOWED_ORIGINS = comma-separated list of JARVIES origins, e.g.
    //   "https://jarvies.example.com,https://staging-jarvies.example.com"
    // Falls back to local dev origins when the env var is not set.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(
        ',',
        env('CORS_ALLOWED_ORIGINS', 'http://localhost:8001,http://127.0.0.1:8001')
    )))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];