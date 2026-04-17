<?php
return [
    'supports_credentials' => true,
    'paths' => ['api/*', 'login', 'logout', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        'http://localhost:8088',
        'http://localhost:8000',
        'https://backend-z57u.onrender.com',
        'https://rmgdbkm3-8000.usw3.devtunnels.ms',
        'https://vtc-back.pages.dev',
        'http://127.0.0.1:8000',
        
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],
    'exposed_headers' => [],

    'max_age' => 0,

];
