<?php

declare(strict_types=1);

namespace App\Controllers;

use Core\Controller;

class AuthController extends Controller
{
    private const FIXED_ADMIN_USERNAME = 'Admin123';
    private const FIXED_ADMIN_PASSWORD = '12345678910';

    public function loginAction(): void
    {
        if (isset($_SESSION['admin']['id'])) {
            $this->redirect('admin/dashboard');
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = isset($_POST['username']) ? trim((string) $_POST['username']) : '';
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

            if ($username === '' || $password === '') {
                $this->view('auth/login', ['error' => 'Tên đăng nhập và mật khẩu là bắt buộc.']);
                return;
            }

            if ($username !== self::FIXED_ADMIN_USERNAME || $password !== self::FIXED_ADMIN_PASSWORD) {
                $this->view('auth/login', ['error' => 'Sai tài khoản hoặc mật khẩu admin cố định.']);
                return;
            }

            $_SESSION['admin'] = [
                'id' => 1,
                'name' => self::FIXED_ADMIN_USERNAME,
                'email' => 'admin-fixed@local',
            ];
            $this->redirect('admin/dashboard');
        }

        $this->view('auth/login');
    }

    public function logoutAction(): void
    {
        unset($_SESSION['admin']);
        session_regenerate_id(true);
        $this->redirect('auth/login');
    }
}
