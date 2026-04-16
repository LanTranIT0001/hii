<?php

declare(strict_types=1);

return [
    'app_name' => 'Pinterest Clone',
    'base_url' => 'http://localhost:8000',
    'db' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => 'db_pinterest',
        'user' => 'root',
        'pass' => '',
        'charset' => 'utf8mb4',
    ],
    'pagination' => [
        'per_page' => 12,
    ],
];
