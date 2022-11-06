<?php

declare(strict_types=1);

return [
    'enabled' => env('TRACING_ENABLED', true),

    'host' => env('TRACING_HOST', 'jaeger.local'),

    'port' => env('TRACING_PORT', 6831),

    'service-name' => env('TRACING_SERVICE_NAME', 'jaeger'),

    'sampling' => [
        'type' => env('TRACING_SAMPLING', 'const'),

        'rate' => env('TRACING_SAMPLING_RATE', 0.5),
    ],

    'send-input' => env('TRACING_SEND_INPUT', false),

    'send-response' => env('TRACING_SEND_RESPONSE', false),

    'middleware' => [
        'excluded_paths' => [
            //
        ],

        'allowed_headers' => [
            '*',
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
