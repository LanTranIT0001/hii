<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;

class Message extends Model
{
    public function findOrCreateConversation(int $userA, int $userB): int
    {
        $sql = 'SELECT cm1.conversation_id
                FROM conversation_members cm1
                INNER JOIN conversation_members cm2
                    ON cm1.conversation_id = cm2.conversation_id
                WHERE cm1.user_id = :user_a AND cm2.user_id = :user_b
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'user_a' => $userA,
            'user_b' => $userB,
        ]);
        $conversationId = $stmt->fetchColumn();
        if ($conversationId !== false) {
            return (int) $conversationId;
        }

        $this->db->beginTransaction();
        $this->db->exec('INSERT INTO conversations (created_at) VALUES (NOW())');
        $newId = (int) $this->db->lastInsertId();
        $insert = $this->db->prepare(
            'INSERT INTO conversation_members (conversation_id, user_id) VALUES (:conversation_id, :user_id)'
        );
        $insert->execute(['conversation_id' => $newId, 'user_id' => $userA]);
        $insert->execute(['conversation_id' => $newId, 'user_id' => $userB]);
        $this->db->commit();

        return $newId;
    }

    public function listUserConversations(int $userId): array
    {
        $sql = 'SELECT c.id,
                       MAX(u.id) AS peer_id,
                       MAX(u.name) AS peer_name,
                       COALESCE(MAX(m.created_at), c.created_at) AS last_message_at,
                       COALESCE(MAX(m.content), "") AS last_message,
                       COALESCE(SUM(CASE WHEN m.sender_id <> :unread_user_id AND m.read_at IS NULL THEN 1 ELSE 0 END), 0) AS unread_count
                FROM conversations c
                INNER JOIN conversation_members me ON me.conversation_id = c.id AND me.user_id = :member_user_id
                LEFT JOIN conversation_members peer ON peer.conversation_id = c.id AND peer.user_id <> :peer_exclude_user_id
                LEFT JOIN users u ON u.id = peer.user_id
                LEFT JOIN messages m ON m.conversation_id = c.id
                GROUP BY c.id, c.created_at
                ORDER BY last_message_at DESC, c.id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'member_user_id' => $userId,
            'peer_exclude_user_id' => $userId,
            'unread_user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listMessages(int $conversationId): array
    {
        $sql = 'SELECT m.id, m.sender_id, m.content, m.created_at, m.shared_pin_id, p.title AS shared_pin_title, p.image_url AS shared_pin_image, p.source_link AS shared_pin_source_link, u.name AS sender_name
                FROM messages m
                INNER JOIN users u ON u.id = m.sender_id
                LEFT JOIN pins p ON p.id = m.shared_pin_id
                WHERE m.conversation_id = :conversation_id
                ORDER BY m.created_at ASC, m.id ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['conversation_id' => $conversationId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markConversationRead(int $conversationId, int $userId): bool
    {
        $sql = 'UPDATE messages
                SET read_at = NOW()
                WHERE conversation_id = :conversation_id
                  AND sender_id <> :user_id
                  AND read_at IS NULL';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);
    }

    public function deleteConversation(int $conversationId, int $userId): bool
    {
        $checkSql = 'SELECT COUNT(*) FROM conversation_members WHERE conversation_id = :conversation_id AND user_id = :user_id';
        $stmt = $this->db->prepare($checkSql);
        $stmt->execute(['conversation_id' => $conversationId, 'user_id' => $userId]);

        if ((int) $stmt->fetchColumn() === 0) {
            return false;
        }

        $this->db->beginTransaction();

        $stmt = $this->db->prepare('DELETE FROM messages WHERE conversation_id = :conversation_id');
        $stmt->execute(['conversation_id' => $conversationId]);

        $stmt = $this->db->prepare('DELETE FROM conversation_members WHERE conversation_id = :conversation_id');
        $stmt->execute(['conversation_id' => $conversationId]);

        $stmt = $this->db->prepare('DELETE FROM conversations WHERE id = :conversation_id');
        $stmt->execute(['conversation_id' => $conversationId]);

        $this->db->commit();

        return true;
    }

    public function listNotifications(int $userId): array
    {
        $sql = <<<'SQL'
SELECT type,
       sender_id,
       sender_name,
       COUNT(*) AS total_count,
       MAX(created_at) AS created_at,
       MAX(conversation_id) AS conversation_id,
       MAX(pin_id) AS pin_id,
       MAX(pin_title) AS pin_title,
       MAX(content) AS content
FROM (
    SELECT 'message' AS type,
           m.sender_id,
           u.name AS sender_name,
           m.created_at,
           m.conversation_id,
           NULL AS pin_id,
           NULL AS pin_title,
           m.content
    FROM messages m
    INNER JOIN conversation_members cm ON cm.conversation_id = m.conversation_id AND cm.user_id = :user_id
    INNER JOIN users u ON u.id = m.sender_id
    WHERE m.sender_id <> :user_id
    UNION ALL
    SELECT 'pin_save' AS type,
           sp.user_id AS sender_id,
           u.name AS sender_name,
           sp.created_at,
           NULL AS conversation_id,
           p.id AS pin_id,
           p.title AS pin_title,
           NULL AS content
    FROM saved_pins sp
    INNER JOIN pins p ON p.id = sp.pin_id
    INNER JOIN users u ON u.id = sp.user_id
    WHERE p.user_id = :user_id
      AND sp.user_id <> :user_id
) AS events
GROUP BY type, sender_id, sender_name
ORDER BY created_at DESC
LIMIT 20
SQL;
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function send(int $conversationId, int $senderId, string $content, ?int $sharedPinId = null): bool
    {
        $sql = 'INSERT INTO messages (conversation_id, sender_id, shared_pin_id, content, created_at)
                VALUES (:conversation_id, :sender_id, :shared_pin_id, :content, NOW())';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'conversation_id' => $conversationId,
            'sender_id' => $senderId,
            'shared_pin_id' => $sharedPinId,
            'content' => $content,
        ]);
    }

    public function isUserInConversation(int $conversationId, int $userId): bool
    {
        $sql = 'SELECT 1
                FROM conversation_members
                WHERE conversation_id = :conversation_id
                  AND user_id = :user_id
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'conversation_id' => $conversationId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }
}
