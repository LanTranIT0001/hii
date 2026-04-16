<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Models\User;
use Core\Controller;
use Core\Database;

class AuthController extends Controller
{
    public function loginAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

            if ($email === '' || $password === '') {
                $this->view('auth/login', ['error' => 'Email và mật khẩu là bắt buộc.']);
                return;
            }

            $db = Database::connection($this->config['db']);
            $userModel = new User($db);
            $user = $userModel->findByEmail($email);

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $this->view('auth/login', ['error' => 'Thông tin đăng nhập không đúng.']);
                return;
            }

            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'username' => $user['username'],
                'name' => $user['name'],
                'avatar' => $user['avatar'] ?? null,
                'email' => $user['email'],
            ];
            $this->redirect('home/index');
        }

        $this->view('auth/login');
    }

    public function registerAction(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = isset($_POST['name']) ? trim((string) $_POST['name']) : '';
            $email = isset($_POST['email']) ? trim((string) $_POST['email']) : '';
            $password = isset($_POST['password']) ? (string) $_POST['password'] : '';

            if ($name === '' || $email === '' || $password === '') {
                $this->view('auth/register', ['error' => 'Vui lòng điền đầy đủ thông tin.']);
                return;
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->view('auth/register', ['error' => 'Email không hợp lệ.']);
                return;
            }

            if (strlen($password) < 8) {
                $this->view('auth/register', ['error' => 'Mật khẩu tối thiểu 8 ký tự.']);
                return;
            }

            $db = Database::connection($this->config['db']);
            $userModel = new User($db);

            if ($userModel->findByEmail($email)) {
                $this->view('auth/register', ['error' => 'Email đã tồn tại.']);
                return;
            }

            $ok = $userModel->create($name, $email, password_hash($password, PASSWORD_DEFAULT));
            if (!$ok) {
                $this->view('auth/register', ['error' => 'Không thể tạo tài khoản.']);
                return;
            }

            $this->redirect('auth/login', ['msg' => 'registered']);
        }

        $this->view('auth/register');
    }

    public function logoutAction(): void
    {
        unset($_SESSION['user']);
        session_regenerate_id(true);
        $this->redirect('home/index');
    }
}
