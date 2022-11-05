<?php

$default = file_exists(env('RELEASES_DB_PATH', env('HOME').'/.releases.sqlite')) ? 'database' : 'array';

return [
    'default' => $default,

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
