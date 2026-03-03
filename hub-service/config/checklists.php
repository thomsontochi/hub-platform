<?php

return [
    'usa' => [
        'fields' => [
            'attributes.ssn' => [
                'label' => 'Social Security Number',
                'rules' => [
                    'required',
                    'string',
                ],
                'messages' => [
                    'required' => 'SSN is required for US employees.',
                ],
            ],
            'salary' => [
                'label' => 'Salary',
                'rules' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
                'messages' => [
                    'required' => 'Salary must be provided.',
                    'min' => 'Salary must be greater than zero.',
                ],
            ],
            'attributes.address' => [
                'label' => 'Address',
                'rules' => [
                    'required',
                    'string',
                ],
                'messages' => [
                    'required' => 'Address cannot be empty.',
                ],
            ],
        ],
    ],
    'germany' => [
        'fields' => [
            'salary' => [
                'label' => 'Salary',
                'rules' => [
                    'required',
                    'numeric',
                    'min:0',
                ],
                'messages' => [
                    'required' => 'Salary must be provided.',
                    'min' => 'Salary must be greater than zero.',
                ],
            ],
            'attributes.goal' => [
                'label' => 'Goal',
                'rules' => [
                    'required',
                    'string',
                ],
                'messages' => [
                    'required' => 'Goal is required.',
                ],
            ],
            'attributes.tax_id' => [
                'label' => 'Tax ID',
                'rules' => [
                    'required',
                    'regex:/^DE\\d{9}$/',
                ],
                'messages' => [
                    'required' => 'Tax ID is mandatory for German employees.',
                    'regex' => 'Tax ID must match the format DE followed by 9 digits.',
                ],
            ],
        ],
    ],
];
