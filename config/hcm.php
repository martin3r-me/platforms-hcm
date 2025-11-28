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
];
