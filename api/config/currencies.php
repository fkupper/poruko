<?php

return [
    'default' => env('APP_DEFAULT_CURRENCY', 'EUR'),

    'available' => [
        'EUR' => [
            'code' => 'EUR',
            'symbol' => '€',
            'name' => 'Euro',
        ],
        'USD' => [
            'code' => 'USD',
            'symbol' => '$',
            'name' => 'US Dollar',
        ],
        'GBP' => [
            'code' => 'GBP',
            'symbol' => '£',
            'name' => 'British Pound',
        ],
        'BRL' => [
            'code' => 'BRL',
            'symbol' => 'R$',
            'name' => 'Brazilian Real',
        ],
        'CAD' => [
            'code' => 'CAD',
            'symbol' => 'C$',
            'name' => 'Canadian Dollar',
        ],
        'AUD' => [
            'code' => 'AUD',
            'symbol' => 'A$',
            'name' => 'Australian Dollar',
        ],
        'JPY' => [
            'code' => 'JPY',
            'symbol' => '¥',
            'name' => 'Japanese Yen',
        ],
    ],
];
