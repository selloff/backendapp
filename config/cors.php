<?php

$allowedOrigins = array_values(array_filter(array_unique(array_merge(
    array_filter([env('FRONTEND_URL')]),
    array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
))));

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Local SPA dev: set FRONTEND_URL=http://localhost:5173 (or add origins via
    | CORS_ALLOWED_ORIGINS). Prefer same-origin requests through the Vite proxy
    | (VITE_API_BASE_URL=/api/v1) to avoid CORS entirely during demo dev.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => $allowedOrigins !== [] ? $allowedOrigins : ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
