<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Laravel CORS Configuration
    |--------------------------------------------------------------------------
    |
    | Allowed origins, methods, headers and max age for CORS requests.
    |
    */

    'paths' => [
        'api/*',
        'sso/*',
        'oauth/*',
    ],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:' . env('FRONTEND_PORT', 3100),
        'http://127.0.0.1:' . env('FRONTEND_PORT', 3100),
        'http://127.0.0.1:8000',  // SIIMUT App (localhost)
        'http://192.168.1.9:8000', // SIIMUT App (production)
        env('FRONTEND_URL', 'http://localhost:3100'),
    ],

    'allowed_origins_patterns' => [
        env('FRONTEND_HOST_PATTERN', '/localhost|192\.168/'),
    ],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
