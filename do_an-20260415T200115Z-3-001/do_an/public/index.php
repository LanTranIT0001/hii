<?php

declare(strict_types=1);

session_start();

$config = require __DIR__ . '/../config/app.php';

spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'Core\\' => __DIR__ . '/../core/',
        'App\\Controllers\\' => __DIR__ . '/../app/controllers/',
        'App\\Models\\' => __DIR__ . '/../app/models/',
    ];

    foreach ($prefixes as $prefix => $baseDir) {
        if (strpos($class, $prefix) !== 0) {
            continue;
        }

        $relativeClass = substr($class, strlen($prefix));
        $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
        if (is_file($file)) {
            require $file;
        }
    }
});

$route = isset($_GET['r']) ? (string) $_GET['r'] : 'home/index';

$router = new Core\Router($config);
$router->dispatch($route);
