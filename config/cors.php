<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing
    |--------------------------------------------------------------------------
    |
    | Configure the cross-origin requests that should be allowed for the API.
    | The website and admin apps run on local Vite ports during development,
    | so we explicitly allow those origins here.
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map(
        static fn (string $origin) => rtrim(trim($origin), '/'),
        explode(',', env(
            'CORS_ALLOWED_ORIGINS',
            'http://localhost:5173,http://127.0.0.1:5173,http://localhost:3000,http://127.0.0.1:3000,https://briksy.elsolveit.com,http://briksy.elsolveit.com'
        ))
    ))),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
