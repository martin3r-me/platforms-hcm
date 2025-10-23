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
                'tariffs' => [
                    'title' => 'Tarife',
                    'icon' => 'heroicon-o-currency-euro',
                    'items' => [
                        'tariff-agreements' => [
                            'title' => 'Tarifverträge',
                            'route' => 'hcm.tariff-agreements.index',
                            'icon' => 'heroicon-o-document-text',
                        ],
                        'tariff-groups' => [
                            'title' => 'Tarifgruppen',
                            'route' => 'hcm.tariff-groups.index',
                            'icon' => 'heroicon-o-squares-2x2',
                        ],
                        'tariff-levels' => [
                            'title' => 'Tarifstufen',
                            'route' => 'hcm.tariff-levels.index',
                            'icon' => 'heroicon-o-bars-3',
                        ],
                        'tariff-rates' => [
                            'title' => 'Tarifsätze',
                            'route' => 'hcm.tariff-rates.index',
                            'icon' => 'heroicon-o-banknotes',
                        ],
                    ],
                ],
            ],
        ],
    ],
];
