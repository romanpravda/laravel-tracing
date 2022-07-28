<?php

return [
    'enabled' => env('TRACING_ENABLED', true),

    'host' => env('TRACING_HOST', 'jaeger.local'),

    'port' => env('TRACING_PORT', 6831),

    'service-name' => env('TRACING_SERVICE_NAME', 'jaeger'),

    'sampling' => [
        'type' => env('TRACING_SAMPLING', 'const'),

        'rate' => 0.5,
    ],

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