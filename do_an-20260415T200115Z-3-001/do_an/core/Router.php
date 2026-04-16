<?php

declare(strict_types=1);

namespace Core;

class Router
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    public function dispatch(string $route): void
    {
        $route = trim($route, '/');
        if ($route === '') {
            $route = 'home/index';
        }

        [$controllerName, $actionName] = array_pad(explode('/', $route), 2, 'index');
        $controllerClass = 'App\\Controllers\\' . ucfirst($controllerName) . 'Controller';
        $method = $actionName . 'Action';

        if (!class_exists($controllerClass)) {
            $this->renderNotFound();
            return;
        }

        $controller = new $controllerClass($this->config);
        if (!method_exists($controller, $method)) {
            $this->renderNotFound();
            return;
        }

        $controller->{$method}();
    }

    private function renderNotFound(): void
    {
        http_response_code(404);
        $appName = $this->config['app_name'];
        $baseUrl = $this->config['base_url'];
        $dbConfig = $this->config['db'];
        require __DIR__ . '/../app/views/layouts/header.php';
        require __DIR__ . '/../app/views/errors/404.php';
        require __DIR__ . '/../app/views/layouts/footer.php';
    }
}
