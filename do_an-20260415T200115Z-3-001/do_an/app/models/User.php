<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class User extends Model
{
    public function findById(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        try {
            $this->ensureBioColumn();
            $sql = 'SELECT id, username, name, bio, avatar, email
                    FROM users
                    WHERE id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            $sql = 'SELECT id, username, name, avatar, email
                    FROM users
                    WHERE id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $row ?: null;
    }

    public function findByEmail(string $email): ?array
    {
        $sql = 'SELECT id, username, name, avatar, email, password_hash
                FROM users
                WHERE email = :email
                  AND (status = :status_active OR status = :status_lower OR status = :status_num)
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'email' => $email,
            'status_active' => 'ACTIVE',
            'status_lower' => 'active',
            'status_num' => '1',
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $sql = 'SELECT id, username, name, avatar, email, password_hash
                    FROM users
                    WHERE email = :email
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['email' => $email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $user ?: null;
    }

    public function findByIdentifier(string $identifier): ?array
    {
        $identifier = trim($identifier);
        if ($identifier === '') {
            return null;
        }

        $query = mb_strtolower($identifier);
        $likeQuery = '%' . $query . '%';

        // First, try exact match on username
        $sql = 'SELECT id, username, name, avatar
                FROM users
                WHERE LOWER(username) = :query
                  AND (status = :status_active OR status = :status_lower OR status = :status_num)
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'query' => $query,
            'status_active' => 'ACTIVE',
            'status_lower' => 'active',
            'status_num' => '1',
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        // Then, exact match on name
        $sql = 'SELECT id, username, name, avatar
                FROM users
                WHERE LOWER(name) = :query
                  AND (status = :status_active OR status = :status_lower OR status = :status_num)
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'query' => $query,
            'status_active' => 'ACTIVE',
            'status_lower' => 'active',
            'status_num' => '1',
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        if (mb_strlen($query) < 2) {
            return null;
        }

        if (mb_strlen($query) < 2) {
            return null;
        }

        // First try prefix match on username or name
        $sql = 'SELECT id, username, name, avatar
                FROM users
                WHERE (LOWER(username) LIKE :prefix_query
                       OR LOWER(name) LIKE :prefix_query)
                  AND (status = :status_active OR status = :status_lower OR status = :status_num)
                ORDER BY CASE WHEN LOWER(username) LIKE :prefix_query THEN 1 ELSE 2 END, username
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'prefix_query' => $query . '%',
            'status_active' => 'ACTIVE',
            'status_lower' => 'active',
            'status_num' => '1',
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            return $user;
        }

        // Allow broader substring matching for longer queries
        if (mb_strlen($query) >= 3) {
            $sql = 'SELECT id, username, name, avatar
                    FROM users
                    WHERE (LOWER(username) LIKE :contains_query
                           OR LOWER(name) LIKE :contains_query)
                      AND (status = :status_active OR status = :status_lower OR status = :status_num)
                    ORDER BY username
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'contains_query' => '%' . $query . '%',
                'status_active' => 'ACTIVE',
                'status_lower' => 'active',
                'status_num' => '1',
            ]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        return null;
    }

    public function create(string $name, string $email, string $passwordHash): bool
    {
        $username = strtolower(preg_replace('/[^a-zA-Z0-9_]/', '', $name) ?? '');
        if ($username === '') {
            $username = 'user' . random_int(1000, 9999);
        }

        $sql = 'INSERT INTO users (username, name, email, password_hash, status, role, created_at)
                VALUES (:username, :name, :email, :password_hash, :status, :role, NOW())';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'username' => $username,
            'name' => $name,
            'email' => $email,
            'password_hash' => $passwordHash,
            'status' => 'ACTIVE',
            'role' => 'USER',
        ]);
    }

    public function listOthers(int $currentUserId): array
    {
        $sql = 'SELECT id, username, name, avatar
                FROM users
                WHERE id <> :user_id
                  AND (status = :status_active OR status = :status_lower OR status = :status_num)
                ORDER BY name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_id' => $currentUserId,
            'status_active' => 'ACTIVE',
            'status_lower' => 'active',
            'status_num' => '1',
        ]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        if ($rows !== []) {
            return $rows;
        }

        $sql = 'SELECT id, username, name, avatar
                FROM users
                WHERE id <> :user_id
                ORDER BY name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $currentUserId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function isFollowing(int $followerId, int $followingId): bool
    {
        if ($followerId <= 0 || $followingId <= 0 || $followerId === $followingId) {
            return false;
        }

        try {
            $this->ensureFollowsTable();
            $sql = 'SELECT 1 FROM follows WHERE follower_id = :follower_id AND following_id = :following_id LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'follower_id' => $followerId,
                'following_id' => $followingId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }

        return (bool) $stmt->fetchColumn();
    }

    public function follow(int $followerId, int $followingId): bool
    {
        if ($followerId <= 0 || $followingId <= 0 || $followerId === $followingId) {
            return false;
        }

        try {
            $this->ensureFollowsTable();
            $sql = 'INSERT IGNORE INTO follows (follower_id, following_id, created_at)
                    VALUES (:follower_id, :following_id, NOW())';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'follower_id' => $followerId,
                'following_id' => $followingId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function unfollow(int $followerId, int $followingId): bool
    {
        if ($followerId <= 0 || $followingId <= 0 || $followerId === $followingId) {
            return false;
        }

        try {
            $this->ensureFollowsTable();
            $sql = 'DELETE FROM follows WHERE follower_id = :follower_id AND following_id = :following_id';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'follower_id' => $followerId,
                'following_id' => $followingId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getFollowerCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            $this->ensureFollowsTable();
            $sql = 'SELECT COUNT(*) FROM follows WHERE following_id = :user_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        } catch (\PDOException $e) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function getFollowingCount(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            $this->ensureFollowsTable();
            $sql = 'SELECT COUNT(*) FROM follows WHERE follower_id = :user_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
        } catch (\PDOException $e) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function updateAvatar(int $userId, string $avatarPath): bool
    {
        if ($userId <= 0 || $avatarPath === '') {
            return false;
        }

        $sql = 'UPDATE users
                SET avatar = :avatar
                WHERE id = :id
                LIMIT 1';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'id' => $userId,
            'avatar' => $avatarPath,
        ]);
    }

    public function updateProfile(int $userId, string $name, string $bio): bool
    {
        if ($userId <= 0 || trim($name) === '') {
            return false;
        }

        $name = trim($name);
        $bio = trim($bio);

        try {
            $this->ensureBioColumn();
            $sql = 'UPDATE users
                    SET name = :name,
                        bio = :bio
                    WHERE id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $userId,
                'name' => $name,
                'bio' => $bio,
            ]);
            return $stmt->rowCount() >= 0;
        } catch (\PDOException $e) {
            $sql = 'UPDATE users
                    SET name = :name
                    WHERE id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $userId,
                'name' => $name,
            ]);
            return $stmt->rowCount() >= 0;
        }
    }

    private function ensureBioColumn(): void
    {
        try {
            $this->db->exec('ALTER TABLE users ADD COLUMN bio TEXT NULL');
        } catch (\PDOException $e) {
            // Column probably exists or schema is incompatible; ignore.
        }
    }

    private function ensureFollowsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS follows (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    follower_id INT UNSIGNED NOT NULL,
                    following_id INT UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY uq_follow_pair (follower_id, following_id),
                    INDEX idx_following (following_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec($sql);
    }
}
