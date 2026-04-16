<?php

declare(strict_types=1);

namespace Core;

class Controller
{
    protected array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    protected function view(string $view, array $data = []): void
    {
        extract($data);
        $appName = $this->config['app_name'];
        $baseUrl = $this->config['base_url'];
        $dbConfig = $this->config['db'];

        require __DIR__ . '/../app/views/layouts/header.php';
        require __DIR__ . '/../app/views/' . $view . '.php';
        require __DIR__ . '/../app/views/layouts/footer.php';
    }

    protected function redirect(string $route, array $query = []): void
    {
        $query = array_merge(['r' => $route], $query);
        $url = 'index.php?' . http_build_query($query);
        header('Location: ' . $url);
        exit;
    }

    protected function currentUserId(): ?int
    {
        if (!isset($_SESSION['user']['id'])) {
            return null;
        }

        return (int) $_SESSION['user']['id'];
    }

    protected function requireAuth(): int
    {
        $userId = $this->currentUserId();
        if ($userId === null) {
            $this->redirect('auth/login');
        }

        return $userId;
    }
}
