<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | The SPA is deployed separately from this API (Render Static Site ->
    | Render Web Service), so every browser call is cross-origin and must be
    | allowed explicitly here.
    |
    | Origins come from the FRONTEND_URL env var as a comma-separated list, so
    | the production domain is set in the Render dashboard rather than being
    | committed. The localhost defaults keep `npm run dev` working untouched.
    |
    | An origin is scheme + host + port with NO trailing slash and NO path:
    |   correct -> https://app.example.com
    |   wrong   -> https://app.example.com/
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('FRONTEND_URL', 'http://localhost:5173,http://localhost:3000'))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 86400,

    // The SPA authenticates with a Bearer token from localStorage, not cookies,
    // so credentialed requests are not required.
    'supports_credentials' => false,

];
