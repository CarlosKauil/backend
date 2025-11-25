<?php

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'http://localhost:5173',
        'http://localhost:5174',
        #'http://varticameta.s3-website.us-east-2.amazonaws.com',
        #'https://rmgdbkm3-8000.usw3.devtunnels.ms',
        #'https://gasparvaldez.github.io/varticaenvironments'
        
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => false,
];