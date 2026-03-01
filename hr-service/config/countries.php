<?php

return [
    'USA' => [
        'validation_rules' => [
            'ssn' => ['required', 'regex:/^\d{3}-\d{2}-\d{4}$/'],
            'address' => ['required', 'string'],
        ],
        'unique_identifier' => 'ssn',
    ],
    'GERMANY' => [
        'validation_rules' => [
            'goal' => ['required', 'string'],
            'tax_id' => ['required', 'regex:/^DE\d{9}$/'],
        ],
        'unique_identifier' => 'tax_id',
    ],
];
