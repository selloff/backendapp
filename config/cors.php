<?php

$allowedOrigins = array_values(array_filter(array_unique(array_merge(
    array_filter([env('FRONTEND_URL'), env('SPA_URL')]),
    array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | SPA requests send Authorization: Bearer, so wildcard origins are unsafe.
    | Set FRONTEND_URL (and optionally CORS_ALLOWED_ORIGINS) on staging/production.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins,

    'allowed_origins_patterns' => [
        '#\Ahttps://([a-z0-9-]+\.)*selloff\.ng\z#',
        '#\Ahttp://localhost(:\d+)?\z#',
        '#\Ahttp://127\.0\.0\.1(:\d+)?\z#',
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
