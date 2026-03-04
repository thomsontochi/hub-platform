<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => (int) env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'hub'),
    'password' => env('RABBITMQ_PASSWORD', 'secret'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'connection_timeout' => (float) env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => (float) env('RABBITMQ_READ_WRITE_TIMEOUT', 3.0),
    'exchange' => env('RABBITMQ_EXCHANGE', 'employee.events'),
    'queue' => env('RABBITMQ_QUEUE', 'hub.employee.events'),
    'routing_keys' => array_filter(
        array_map('trim', explode(',', env('RABBITMQ_ROUTING_KEYS', 'employee.*.*')))
    ),
    'prefetch_count' => (int) env('RABBITMQ_PREFETCH_COUNT', 10),
    'retry' => [
        'exchange' => env('RABBITMQ_RETRY_EXCHANGE', 'employee.events.retry'),
        'queue' => env('RABBITMQ_RETRY_QUEUE', 'hub.employee.events.retry'),
        'routing_key' => env('RABBITMQ_RETRY_ROUTING_KEY', 'hub.employee.events.retry'),
        'ttl' => (int) env('RABBITMQ_RETRY_TTL', 5000),
        'max_attempts' => (int) env('RABBITMQ_RETRY_MAX_ATTEMPTS', 3),
    ],
    'dead_letter' => [
        'exchange' => env('RABBITMQ_DLQ_EXCHANGE', 'employee.events.dlq'),
        'queue' => env('RABBITMQ_DLQ_QUEUE', 'hub.employee.events.dlq'),
        'routing_key' => env('RABBITMQ_DLQ_ROUTING_KEY', 'hub.employee.events.dlq'),
    ],
];
