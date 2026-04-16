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

    protected function currentAdminId(): ?int
    {
        if (!isset($_SESSION['admin']['id'])) {
            return null;
        }

        return (int) $_SESSION['admin']['id'];
    }

    protected function requireAdmin(): int
    {
        $adminId = $this->currentAdminId();
        if ($adminId === null) {
            $this->redirect('auth/login');
        }

        return $adminId;
    }
}
