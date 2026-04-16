<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;
use PDOException;

class Notification extends Model
{
    public function createLikeNotification(int $actorId, int $pinId, int $receiverId): bool
    {
        return $this->create($actorId, $receiverId, 'like', $pinId, null, null);
    }

    public function createCommentNotification(int $actorId, int $pinId, int $receiverId, string $comment): bool
    {
        return $this->create($actorId, $receiverId, 'comment', $pinId, null, $comment);
    }

    public function createFollowNotification(int $actorId, int $receiverId): bool
    {
        return $this->create($actorId, $receiverId, 'follow', null, null, null);
    }

    public function listByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureTable();
            $sql = 'SELECT n.id,
                           n.type,
                           n.created_at,
                           n.is_read,
                           n.pin_id,
                           n.comment_text,
                           u.name AS actor_name,
                           p.title AS pin_title
                    FROM notifications n
                    INNER JOIN users u ON u.id = n.actor_id
                    LEFT JOIN pins p ON p.id = n.pin_id
                    WHERE n.receiver_id = :receiver_id
                    ORDER BY n.created_at DESC, n.id DESC
                    LIMIT 40';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['receiver_id' => $userId]);
        } catch (PDOException $e) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countUnreadByUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        try {
            $this->ensureTable();
            $sql = 'SELECT COUNT(*)
                    FROM notifications
                    WHERE receiver_id = :receiver_id
                      AND is_read = 0';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['receiver_id' => $userId]);
        } catch (PDOException $e) {
            return 0;
        }

        return (int) $stmt->fetchColumn();
    }

    public function markAllRead(int $userId): bool
    {
        if ($userId <= 0) {
            return false;
        }

        try {
            $this->ensureTable();
            $sql = 'UPDATE notifications
                    SET is_read = 1, read_at = NOW()
                    WHERE receiver_id = :receiver_id
                      AND is_read = 0';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(['receiver_id' => $userId]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function markRead(int $notificationId, int $userId): bool
    {
        if ($notificationId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $this->ensureTable();
            $sql = 'UPDATE notifications
                    SET is_read = 1, read_at = NOW()
                    WHERE id = :id
                      AND receiver_id = :receiver_id
                      AND is_read = 0';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id' => $notificationId,
                'receiver_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    private function create(
        int $actorId,
        int $receiverId,
        string $type,
        ?int $pinId,
        ?int $targetUserId,
        ?string $commentText
    ): bool {
        if ($actorId <= 0 || $receiverId <= 0 || $actorId === $receiverId) {
            return false;
        }

        try {
            $this->ensureTable();
            $sql = 'INSERT INTO notifications (actor_id, receiver_id, type, pin_id, target_user_id, comment_text, is_read, read_at, created_at)
                    VALUES (:actor_id, :receiver_id, :type, :pin_id, :target_user_id, :comment_text, 0, NULL, NOW())';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'actor_id' => $actorId,
                'receiver_id' => $receiverId,
                'type' => $type,
                'pin_id' => $pinId,
                'target_user_id' => $targetUserId,
                'comment_text' => $commentText,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    private function ensureTable(): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS notifications (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    actor_id INT UNSIGNED NOT NULL,
                    receiver_id INT UNSIGNED NOT NULL,
                    type VARCHAR(20) NOT NULL,
                    pin_id INT UNSIGNED NULL,
                    target_user_id INT UNSIGNED NULL,
                    comment_text TEXT NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    read_at DATETIME NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_notifications_receiver (receiver_id),
                    INDEX idx_notifications_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        $this->db->exec($sql);
        try {
            $this->db->exec('ALTER TABLE notifications ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0');
        } catch (PDOException $e) {
            // Ignore if column already exists.
        }
        try {
            $this->db->exec('ALTER TABLE notifications ADD COLUMN read_at DATETIME NULL');
        } catch (PDOException $e) {
            // Ignore if column already exists.
        }
    }
}
