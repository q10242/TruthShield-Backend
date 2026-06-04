<?php

$frontendUrl = rtrim((string) env('FRONTEND_URL', ''), '/');
$configuredOrigins = array_filter(array_map(
    'trim',
    explode(',', env('TRUTHSHIELD_ALLOWED_ORIGINS', '')),
));
$defaultOrigins = array_filter([
    'http://127.0.0.1:5173',
    'http://localhost:5173',
    'http://127.0.0.1:15173',
    'http://localhost:15173',
    'https://truth-shield.otus.tw',
    'https://truthshield.otus.tw',
    'https://truthshield-frontend-676428765728.asia-east1.run.app',
    $frontendUrl,
]);

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_unique([...$defaultOrigins, ...$configuredOrigins])),
    'allowed_origins_patterns' => [
        '#^chrome-extension://[a-p]{32}$#',
    ],
    'allowed_headers' => [
        'Accept',
        'Accept-Language',
        'Authorization',
        'Content-Type',
        'X-Requested-With',
        'X-TruthShield-Client',
        'X-TruthShield-Extension-Nonce',
        'X-TruthShield-Extension-Signature',
        'X-TruthShield-Install-Id',
        'X-TruthShield-Read-Seconds',
        'X-TruthShield-Scroll-Depth',
    ],
    'exposed_headers' => [],
    'max_age' => 3600,
    'supports_credentials' => false,
];
