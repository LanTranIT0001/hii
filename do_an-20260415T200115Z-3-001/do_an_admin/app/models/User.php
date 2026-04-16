<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class User extends Model
{
    public function findAdminByEmail(string $email): ?array
    {
        $sql = 'SELECT id, username, name, email, password_hash, role, status
                FROM users
                WHERE email = :email
                  AND (
                        role = :role_admin_upper
                        OR role = :role_admin_lower
                      )
                  AND (
                        status = :status_active_upper
                        OR status = :status_active_lower
                        OR status = :status_active_num
                      )
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'email' => $email,
            'role_admin_upper' => 'ADMIN',
            'role_admin_lower' => 'admin',
            'status_active_upper' => 'ACTIVE',
            'status_active_lower' => 'active',
            'status_active_num' => '1',
        ]);
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);

        return $admin ?: null;
    }
}
