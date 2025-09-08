<?php

return [
    'name' => 'HCM',
    'description' => 'HCM Module',
    'version' => '1.0.0',
    
    'routing' => [
        'prefix' => 'hcm',
        'middleware' => ['web', 'auth'],
    ],
    
    'guard' => 'web',
    
    'navigation' => [
        'main' => [
            'hcm' => [
                'title' => 'HCM',
                'icon' => 'heroicon-o-users',
                'route' => 'hcm.dashboard',
            ],
        ],
    ],
    
    'sidebar' => [
        'hcm' => [
            'title' => 'HCM',
            'icon' => 'heroicon-o-users',
            'items' => [
                'dashboard' => [
                    'title' => 'Dashboard',
                    'route' => 'hcm.dashboard',
                    'icon' => 'heroicon-o-home',
                ],
                'employers' => [
                    'title' => 'Arbeitgeber',
                    'route' => 'hcm.employers.index',
                    'icon' => 'heroicon-o-building-office',
                ],
                'employees' => [
                    'title' => 'Mitarbeiter',
                    'route' => 'hcm.employees.index',
                    'icon' => 'heroicon-o-user-group',
                ],
            ],
        ],
    ],
];
