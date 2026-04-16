<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Notification;
use Core\Controller;
use Core\Database;
use PDOException;

class NotificationController extends Controller
{
    public function indexAction(): void
    {
        $userId = $this->requireAuth();
        $db = Database::connection($this->config['db']);
        $notificationModel = new Notification($db);

        try {
            $notifications = $notificationModel->listByUser($userId);
        } catch (PDOException $e) {
            $notifications = [];
        }

        $this->view('notifications/index', [
            'notifications' => $notifications,
        ]);
    }

    public function markReadAction(): void
    {
        $userId = $this->requireAuth();
        $db = Database::connection($this->config['db']);
        $notificationModel = new Notification($db);

        try {
            $notificationModel->markAllRead($userId);
        } catch (PDOException $e) {
            // Ignore DB errors to keep UX stable.
        }

        $this->redirect('notification/index');
    }

    public function openAction(): void
    {
        $userId = $this->requireAuth();
        $notificationId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
        $next = isset($_GET['next']) ? (string) $_GET['next'] : '';

        $db = Database::connection($this->config['db']);
        $notificationModel = new Notification($db);
        try {
            $notificationModel->markRead($notificationId, $userId);
        } catch (PDOException $e) {
            // Ignore DB errors to keep UX stable.
        }

        if ($next !== '' && strpos($next, 'index.php?') === 0) {
            header('Location: ' . $next);
            exit;
        }

        $this->redirect('notification/index');
    }
}
