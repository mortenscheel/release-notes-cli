<?php

return [
    'default' => (new \App\LocalConfig)->databaseExists() ? 'database' : 'array',

    'stores' => [
        'database' => [
            'driver' => 'database',
            'table' => 'cache',
            'connection' => 'sqlite',
            'lock_connection' => null,
        ],
        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],
    ],
];
