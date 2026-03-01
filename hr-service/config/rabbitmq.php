<?php

return [
    'host' => env('RABBITMQ_HOST', 'rabbitmq'),
    'port' => env('RABBITMQ_PORT', 5672),
    'user' => env('RABBITMQ_USER', 'guest'),
    'password' => env('RABBITMQ_PASSWORD', 'guest'),
    'vhost' => env('RABBITMQ_VHOST', '/'),
    'exchange' => env('RABBITMQ_EXCHANGE', 'employee.events'),
    'connection_timeout' => env('RABBITMQ_CONNECTION_TIMEOUT', 3.0),
    'read_write_timeout' => env('RABBITMQ_RW_TIMEOUT', 3.0),
];
