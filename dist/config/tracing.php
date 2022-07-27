<?php

declare(strict_types=1);

return [
    'host' => env('TRACING_HOST', 'jaeger.local'),

    'port' => env('TRACING_PORT', 6831),

    'service-name' => env('TRACING_SERVICE_NAME', 'jaeger'),

    'middleware' => [
        'excluded_paths' => [
            //
        ],

        'allowed_headers' => [
            '*'
        ],

        'sensitive_headers' => [
            'authorization',
        ],

        'sensitive_input' => [
            //
        ],

        'payload' => [
            'content_types' => [
                'application/json',
            ],
        ],
    ],
];