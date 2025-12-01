<?php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:8088',
        'http://localhost:8000',
        'https://gasparvaldez.github.io',
        'https://backend-z57u.onrender.com',
        'https://rmgdbkm3-8000.usw3.devtunnels.ms',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    // <<< CAMBIA ESTO A TRUE
    'supports_credentials' => true,
];
