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
];
