<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;
use PDOException;

class Board extends Model
{
    public function listPublicBoards(?int $viewerId = null): array
    {
        $params = ['privacy' => 'PUBLIC'];
        if ($viewerId !== null && $viewerId > 0) {
            $this->ensureSavedBoardsTable();
            $sql = 'SELECT b.id,
                           b.user_id,
                           b.name,
                           b.description,
                           b.privacy,
                           b.created_at,
                           u.name AS owner_name,
                           CASE WHEN sb.id IS NULL THEN 0 ELSE 1 END AS is_saved
                    FROM boards b
                    INNER JOIN users u ON u.id = b.user_id
                    LEFT JOIN saved_boards sb
                        ON sb.board_id = b.id AND sb.user_id = :viewer_id
                    WHERE b.privacy = :privacy
                    ORDER BY b.created_at DESC';
            $params['viewer_id'] = $viewerId;
        } else {
            $sql = 'SELECT b.id,
                           b.user_id,
                           b.name,
                           b.description,
                           b.privacy,
                           b.created_at,
                           u.name AS owner_name,
                           0 AS is_saved
                    FROM boards b
                    INNER JOIN users u ON u.id = b.user_id
                    WHERE b.privacy = :privacy
                    ORDER BY b.created_at DESC';
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Danh sách board công khai kèm số ghim và tối đa 3 ảnh preview (mới thêm trước).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listPublicBoardsWithPreviews(?int $viewerId = null): array
    {
        $boards = $this->listPublicBoards($viewerId);
        if ($boards === []) {
            return [];
        }

        $ids = array_values(array_filter(array_map('intval', array_column($boards, 'id')), static function (int $id): bool {
            return $id > 0;
        }));
        if ($ids === []) {
            return $boards;
        }

        $counts = $this->getPinCountsForBoards($ids);
        $previews = $this->getPreviewImageUrlsForBoards($ids);

        foreach ($boards as &$b) {
            $id = (int) ($b['id'] ?? 0);
            $b['pin_count'] = $counts[$id] ?? 0;
            $b['preview_images'] = $previews[$id] ?? [];
        }
        unset($b);

        return $boards;
    }

    /**
     * @param array<int, int> $boardIds
     * @return array<int, int>
     */
    public function getPinCountsForBoards(array $boardIds): array
    {
        $boardIds = array_values(array_unique(array_filter(array_map('intval', $boardIds), static function (int $id): bool {
            return $id > 0;
        })));
        if ($boardIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($boardIds as $i => $bid) {
            $k = 'b' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = $bid;
        }

        try {
            $sql = 'SELECT board_id, COUNT(*) AS cnt
                    FROM board_pins
                    WHERE board_id IN (' . implode(', ', $placeholders) . ')
                    GROUP BY board_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $out[(int) ($row['board_id'] ?? 0)] = (int) ($row['cnt'] ?? 0);
        }

        return $out;
    }

    /**
     * Tối đa 3 URL ảnh mỗi board (mới thêm vào board trước).
     *
     * @param array<int, int> $boardIds
     * @return array<int, array<int, string>>
     */
    public function getPreviewImageUrlsForBoards(array $boardIds): array
    {
        $boardIds = array_values(array_unique(array_filter(array_map('intval', $boardIds), static function (int $id): bool {
            return $id > 0;
        })));
        if ($boardIds === []) {
            return [];
        }

        $placeholders = [];
        $params = [];
        foreach ($boardIds as $i => $bid) {
            $k = 'b' . $i;
            $placeholders[] = ':' . $k;
            $params[$k] = $bid;
        }

        try {
            $sql = 'SELECT bp.board_id, p.image_url
                    FROM board_pins bp
                    INNER JOIN pins p ON p.id = bp.pin_id
                    WHERE bp.board_id IN (' . implode(', ', $placeholders) . ')
                    ORDER BY bp.board_id ASC, bp.added_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $bid = (int) ($row['board_id'] ?? 0);
            $url = trim((string) ($row['image_url'] ?? ''));
            if ($bid <= 0 || $url === '') {
                continue;
            }
            if (!isset($out[$bid])) {
                $out[$bid] = [];
            }
            if (count($out[$bid]) >= 3) {
                continue;
            }
            $out[$bid][] = $url;
        }

        return $out;
    }

    public function listByUser(int $userId): array
    {
        $sql = 'SELECT id, name, description, privacy
                FROM boards
                WHERE user_id = :user_id
                ORDER BY created_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listByUserWithFilter(int $userId, string $privacyFilter = 'all'): array
    {
        if ($userId <= 0) {
            return [];
        }

        $filter = strtoupper(trim($privacyFilter));
        $params = ['user_id' => $userId];

        try {
            $sql = 'SELECT b.id,
                           b.name,
                           b.description,
                           b.privacy,
                           b.created_at,
                           COUNT(bp.pin_id) AS pin_count
                    FROM boards b
                    LEFT JOIN board_pins bp ON bp.board_id = b.id
                    WHERE b.user_id = :user_id';

            if ($filter === 'PUBLIC' || $filter === 'PRIVATE') {
                $sql .= ' AND b.privacy = :privacy';
                $params['privacy'] = $filter;
            }

            $sql .= ' GROUP BY b.id, b.name, b.description, b.privacy, b.created_at
                      ORDER BY b.created_at DESC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $sql = 'SELECT b.id,
                           b.name,
                           b.description,
                           b.privacy,
                           b.created_at,
                           0 AS pin_count
                    FROM boards b
                    WHERE b.user_id = :user_id';
            if ($filter === 'PUBLIC' || $filter === 'PRIVATE') {
                $sql .= ' AND b.privacy = :privacy';
            }
            $sql .= ' ORDER BY b.created_at DESC';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    public function listSavedBoardsByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureSavedBoardsTable();
            $sql = 'SELECT b.id,
                           b.name,
                           b.description,
                           b.privacy,
                           u.name AS owner_name,
                           sb.created_at AS saved_at
                    FROM saved_boards sb
                    INNER JOIN boards b ON b.id = sb.board_id
                    INNER JOIN users u ON u.id = b.user_id
                    WHERE sb.user_id = :user_id
                      AND b.user_id <> :user_id_exclude_own
                    ORDER BY sb.created_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $userId,
                'user_id_exclude_own' => $userId,
            ]);
        } catch (\PDOException $e) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function saveBoard(int $userId, int $boardId): bool
    {
        if ($userId <= 0 || $boardId <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('SELECT user_id FROM boards WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $boardId]);
            $ownerId = (int) $stmt->fetchColumn();
            if ($ownerId <= 0 || $ownerId === $userId) {
                return false;
            }

            $this->ensureSavedBoardsTable();
            $sql = 'INSERT IGNORE INTO saved_boards (user_id, board_id, created_at)
                    VALUES (:user_id, :board_id, NOW())';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'board_id' => $boardId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function unsaveBoard(int $userId, int $boardId): bool
    {
        if ($userId <= 0 || $boardId <= 0) {
            return false;
        }

        try {
            $this->ensureSavedBoardsTable();
            $sql = 'DELETE FROM saved_boards
                    WHERE user_id = :user_id AND board_id = :board_id';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'board_id' => $boardId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function create(int $userId, string $name, string $description, string $privacy): bool
    {
        $sql = 'INSERT INTO boards (user_id, name, description, privacy, created_at)
                VALUES (:user_id, :name, :description, :privacy, NOW())';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'description' => $description,
            'privacy' => $privacy,
        ]);
    }

    public function addPin(int $boardId, int $pinId): bool
    {
        $sql = 'INSERT IGNORE INTO board_pins (board_id, pin_id, added_at) VALUES (:board_id, :pin_id, NOW())';
        $stmt = $this->db->prepare($sql);

        return $stmt->execute([
            'board_id' => $boardId,
            'pin_id' => $pinId,
        ]);
    }

    public function isOwnedByUser(int $boardId, int $userId): bool
    {
        if ($boardId <= 0 || $userId <= 0) {
            return false;
        }

        $sql = 'SELECT 1
                FROM boards
                WHERE id = :board_id
                  AND user_id = :user_id
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'board_id' => $boardId,
            'user_id' => $userId,
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function listSavedPinsNotInBoard(int $userId, int $boardId): array
    {
        if ($userId <= 0 || $boardId <= 0) {
            return [];
        }

        try {
            $sql = 'SELECT p.id,
                           p.title,
                           p.image_url,
                           p.description,
                           p.created_at
                    FROM saved_pins sp
                    INNER JOIN pins p ON p.id = sp.pin_id
                    LEFT JOIN board_pins bp
                        ON bp.pin_id = sp.pin_id
                       AND bp.board_id = :board_id
                    WHERE sp.user_id = :user_id
                      AND bp.pin_id IS NULL
                    ORDER BY sp.created_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'board_id' => $boardId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return [];
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function canAttachSavedPinToOwnedBoard(int $userId, int $boardId, int $pinId): bool
    {
        if ($userId <= 0 || $boardId <= 0 || $pinId <= 0) {
            return false;
        }

        try {
            $sql = 'SELECT 1
                    FROM boards b
                    INNER JOIN saved_pins sp ON sp.user_id = b.user_id
                    WHERE b.id = :board_id
                      AND b.user_id = :user_id
                      AND sp.pin_id = :pin_id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'board_id' => $boardId,
                'user_id' => $userId,
                'pin_id' => $pinId,
            ]);
        } catch (PDOException $e) {
            return false;
        }

        return (bool) $stmt->fetchColumn();
    }

    public function removePinFromUserBoards(int $userId, int $pinId): bool
    {
        if ($userId <= 0 || $pinId <= 0) {
            return false;
        }

        try {
            $sql = 'DELETE bp
                    FROM board_pins bp
                    INNER JOIN boards b ON b.id = bp.board_id
                    WHERE b.user_id = :user_id
                      AND bp.pin_id = :pin_id';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'user_id' => $userId,
                'pin_id' => $pinId,
            ]);
        } catch (\PDOException $e) {
            return false;
        }
    }

    public function getUserBoardMapForPins(int $userId, array $pinIds): array
    {
        $pinIds = array_values(array_filter(array_map('intval', $pinIds), static function (int $id): bool {
            return $id > 0;
        }));
        if ($userId <= 0 || $pinIds === []) {
            return [];
        }

        $placeholders = [];
        $params = ['user_id' => $userId];
        foreach ($pinIds as $index => $pinId) {
            $key = 'pin_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $pinId;
        }

        try {
            $sql = 'SELECT bp.pin_id, MIN(bp.board_id) AS board_id
                    FROM board_pins bp
                    INNER JOIN boards b ON b.id = bp.board_id
                    WHERE b.user_id = :user_id
                      AND bp.pin_id IN (' . implode(', ', $placeholders) . ')
                    GROUP BY bp.pin_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (\PDOException $e) {
            return [];
        }

        $map = [];
        foreach ($rows as $row) {
            $map[(int) ($row['pin_id'] ?? 0)] = (int) ($row['board_id'] ?? 0);
        }

        return $map;
    }

    public function pins(int $boardId): array
    {
        $this->purgeUnsavedPinsFromBoard($boardId);

        $sql = 'SELECT p.id, p.title, p.image_url, p.description, p.created_at, u.name AS author_name
                FROM board_pins bp
                INNER JOIN pins p ON p.id = bp.pin_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE bp.board_id = :board_id
                ORDER BY bp.added_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['board_id' => $boardId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function find(int $boardId): ?array
    {
        $sql = 'SELECT b.id, b.user_id, b.name, b.description, b.privacy, u.name AS owner_name
                FROM boards b
                INNER JOIN users u ON u.id = b.user_id
                WHERE b.id = :id
                LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['id' => $boardId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function purgeUnsavedPinsFromBoard(int $boardId): void
    {
        if ($boardId <= 0) {
            return;
        }

        try {
            $sql = 'DELETE bp
                    FROM board_pins bp
                    INNER JOIN boards b ON b.id = bp.board_id
                    LEFT JOIN saved_pins sp
                        ON sp.pin_id = bp.pin_id
                       AND sp.user_id = b.user_id
                    WHERE bp.board_id = :board_id
                      AND sp.pin_id IS NULL';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['board_id' => $boardId]);
        } catch (PDOException $e) {
            // Ignore cleanup failure to keep board page available.
        }
    }

    public function deleteOwnedBoard(int $boardId, int $userId): bool
    {
        if ($boardId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $stmt = $this->db->prepare('SELECT id FROM boards WHERE id = :id AND user_id = :user_id LIMIT 1');
            $stmt->execute(['id' => $boardId, 'user_id' => $userId]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                return false;
            }

            try {
                $stmt = $this->db->prepare('DELETE FROM board_pins WHERE board_id = :board_id');
                $stmt->execute(['board_id' => $boardId]);
            } catch (PDOException $e) {
                // Ignore to keep app stable on older schema.
            }

            try {
                $this->ensureSavedBoardsTable();
                $stmt = $this->db->prepare('DELETE FROM saved_boards WHERE board_id = :board_id');
                $stmt->execute(['board_id' => $boardId]);
            } catch (PDOException $e) {
                // Ignore to keep app stable on older schema.
            }

            $stmt = $this->db->prepare('DELETE FROM boards WHERE id = :id AND user_id = :user_id');
            $stmt->execute(['id' => $boardId, 'user_id' => $userId]);

            return $stmt->rowCount() > 0;
        } catch (PDOException $e) {
            return false;
        }
    }

    private function ensureSavedBoardsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS saved_boards (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    user_id INT UNSIGNED NOT NULL,
                    board_id INT UNSIGNED NOT NULL,
                    created_at DATETIME NOT NULL,
                    UNIQUE KEY uq_saved_board (user_id, board_id),
                    INDEX idx_saved_boards_board (board_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec($sql);
    }
}
