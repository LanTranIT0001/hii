<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\Pin;
use Core\Controller;
use Core\Database;

class AdminController extends Controller
{
    public function dashboardAction(): void
    {
        $this->requireAdmin();

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $reportedPins = $pinModel->listReportedPins();

        $this->view('admin/dashboard', [
            'reportedPins' => $reportedPins,
        ]);
    }

    public function deletePinAction(): void
    {
        $this->requireAdmin();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->redirect('admin/dashboard');
        }

        $pinId = isset($_POST['pin_id']) ? (int) $_POST['pin_id'] : 0;
        if ($pinId <= 0) {
            $this->redirect('admin/dashboard', ['msg' => 'invalid']);
        }

        $db = Database::connection($this->config['db']);
        $pinModel = new Pin($db);
        $deleted = $pinModel->deletePinByAdmin($pinId);

        $this->redirect('admin/dashboard', ['msg' => $deleted ? 'deleted' : 'error']);
    }
}
