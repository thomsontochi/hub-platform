<?php

return [
    'cache' => [
        'steps' => env('UI_STEPS_CACHE_TTL', 900),
        'schema' => env('UI_SCHEMA_CACHE_TTL', 900),
        'employees' => env('UI_EMPLOYEES_CACHE_TTL', 120),
    ],

    'countries' => [
        'usa' => [
            'steps' => [
                [
                    'id' => 'dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'heroicon-o-home',
                    'path' => '/dashboard',
                    'order' => 1,
                ],
                [
                    'id' => 'employees',
                    'label' => 'Employees',
                    'icon' => 'heroicon-o-users',
                    'path' => '/employees',
                    'order' => 2,
                ],
            ],
            'columns' => [
                [
                    'field' => 'name',
                    'key' => 'name',
                    'label' => 'First Name',
                    'type' => 'text',
                ],
                [
                    'field' => 'last_name',
                    'key' => 'last_name',
                    'label' => 'Last Name',
                    'type' => 'text',
                ],
                [
                    'field' => 'salary',
                    'key' => 'salary',
                    'label' => 'Salary',
                    'type' => 'currency',
                ],
                [
                    'field' => 'ssn',
                    'key' => 'attributes.ssn',
                    'label' => 'SSN',
                    'type' => 'text',
                    'mask' => true,
                ],
            ],
            'widgets' => [
                'dashboard' => [
                    [
                        'id' => 'employee_count',
                        'type' => 'metric',
                        'label' => 'Employee Count',
                        'icon' => 'heroicon-o-users',
                        'data_source' => 'employees.count',
                        'channels' => ['hub.country.usa.employees'],
                    ],
                    [
                        'id' => 'average_salary',
                        'type' => 'metric',
                        'label' => 'Average Salary',
                        'icon' => 'heroicon-o-banknotes',
                        'data_source' => 'employees.average_salary',
                        'channels' => ['hub.country.usa.employees'],
                    ],
                    [
                        'id' => 'completion_rate',
                        'type' => 'metric',
                        'label' => 'Checklist Completion',
                        'icon' => 'heroicon-o-check-circle',
                        'data_source' => 'checklists.completion_rate',
                        'channels' => ['hub.country.usa.checklist'],
                    ],
                ],
                'employees' => [
                    [
                        'id' => 'employees_table',
                        'type' => 'table',
                        'label' => 'Employee Directory',
                        'icon' => 'heroicon-o-rectangle-stack',
                        'data_source' => 'employees.list',
                        'channels' => ['hub.country.usa.employees'],
                    ],
                ],
            ],
        ],

        'germany' => [
            'steps' => [
                [
                    'id' => 'dashboard',
                    'label' => 'Dashboard',
                    'icon' => 'heroicon-o-home',
                    'path' => '/dashboard',
                    'order' => 1,
                ],
                [
                    'id' => 'employees',
                    'label' => 'Employees',
                    'icon' => 'heroicon-o-users',
                    'path' => '/employees',
                    'order' => 2,
                ],
                [
                    'id' => 'documentation',
                    'label' => 'Documentation',
                    'icon' => 'heroicon-o-document-text',
                    'path' => '/documentation',
                    'order' => 3,
                ],
            ],
            'columns' => [
                [
                    'field' => 'name',
                    'key' => 'name',
                    'label' => 'First Name',
                    'type' => 'text',
                ],
                [
                    'field' => 'last_name',
                    'key' => 'last_name',
                    'label' => 'Last Name',
                    'type' => 'text',
                ],
                [
                    'field' => 'salary',
                    'key' => 'salary',
                    'label' => 'Salary',
                    'type' => 'currency',
                ],
                [
                    'field' => 'goal',
                    'key' => 'attributes.goal',
                    'label' => 'Goal',
                    'type' => 'text',
                ],
            ],
            'widgets' => [
                'dashboard' => [
                    [
                        'id' => 'employee_count',
                        'type' => 'metric',
                        'label' => 'Employee Count',
                        'icon' => 'heroicon-o-users',
                        'data_source' => 'employees.count',
                        'channels' => ['hub.country.germany.employees'],
                    ],
                    [
                        'id' => 'goal_tracking',
                        'type' => 'metric',
                        'label' => 'Goal Tracking',
                        'icon' => 'heroicon-o-chart-bar',
                        'data_source' => 'employees.goal_tracking',
                        'channels' => ['hub.country.germany.employees'],
                    ],
                ],
                'employees' => [
                    [
                        'id' => 'employees_table',
                        'type' => 'table',
                        'label' => 'Employee Directory',
                        'icon' => 'heroicon-o-rectangle-stack',
                        'data_source' => 'employees.list',
                        'channels' => ['hub.country.germany.employees'],
                    ],
                ],
                'documentation' => [
                    [
                        'id' => 'country_docs',
                        'type' => 'document',
                        'label' => 'Country Guidelines',
                        'icon' => 'heroicon-o-book-open',
                        'data_source' => 'docs.country.guidelines',
                        'channels' => [],
                    ],
                ],
            ],
        ],
    ],
];
