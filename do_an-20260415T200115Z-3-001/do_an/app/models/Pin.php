<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;
use PDOException;

class Pin extends Model
{
    public function reportPin(int $pinId, int $reportedBy, string $reason = ''): bool
    {
        if ($pinId <= 0 || $reportedBy <= 0) {
            return false;
        }

        $this->ensureReportsTable();
        $pin = $this->findById($pinId);
        if (!$pin) {
            return false;
        }

        if ((int) ($pin['user_id'] ?? 0) === $reportedBy) {
            return false;
        }

        $reason = trim($reason);
        if ($reason === '') {
            $reason = 'Nội dung vi phạm hoặc không phù hợp';
        }
        if (mb_strlen($reason) > 255) {
            $reason = mb_substr($reason, 0, 255);
        }

        try {
            $sql = 'SELECT id
                    FROM pin_reports
                    WHERE pin_id = :pin_id
                      AND reported_by = :reported_by
                      AND status = :status
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pin_id' => $pinId,
                'reported_by' => $reportedBy,
                'status' => 'PENDING',
            ]);
            if ($stmt->fetch(PDO::FETCH_ASSOC)) {
                return true;
            }

            $sql = 'INSERT INTO pin_reports (pin_id, reported_by, reason, status, created_at)
                    VALUES (:pin_id, :reported_by, :reason, :status, NOW())';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'pin_id' => $pinId,
                'reported_by' => $reportedBy,
                'reason' => $reason,
                'status' => 'PENDING',
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function updateOwnedPin(
        int $pinId,
        int $userId,
        string $title,
        string $description,
        string $categoryLabel = ''
    ): bool
    {
        if ($pinId <= 0 || $userId <= 0 || $title === '') {
            return false;
        }

        try {
            $this->ensureCategoryLabelColumn();
            $sql = 'UPDATE pins
                    SET title = :title,
                        description = :description,
                        category_label = :category_label
                    WHERE id = :id
                      AND user_id = :user_id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'title' => $title,
                'description' => $description,
                'category_label' => $this->cleanCategoryLabel($categoryLabel),
                'id' => $pinId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function deleteOwnedPin(int $pinId, int $userId): bool
    {
        if ($pinId <= 0 || $userId <= 0) {
            return false;
        }

        try {
            $pin = $this->findById($pinId);
            if (!$pin || (int) ($pin['user_id'] ?? 0) !== $userId) {
                return false;
            }

            // Remove related rows first to avoid foreign-key violations.
            $relatedDeletes = [
                'DELETE FROM board_pins WHERE pin_id = :pin_id',
                'DELETE FROM saved_pins WHERE pin_id = :pin_id',
                'DELETE FROM likes WHERE pin_id = :pin_id',
                'DELETE FROM comments WHERE pin_id = :pin_id',
                'DELETE FROM notifications WHERE pin_id = :pin_id',
            ];
            foreach ($relatedDeletes as $sql) {
                try {
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute(['pin_id' => $pinId]);
                } catch (PDOException $e) {
                    // Ignore on old schema where table does not exist.
                }
            }

            $stmt = $this->db->prepare('DELETE FROM pins WHERE id = :id AND user_id = :user_id LIMIT 1');
            return $stmt->execute([
                'id' => $pinId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function listByUser(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        try {
            $this->ensureCategoryLabelColumn();
            $sql = 'SELECT p.id, p.title, p.image_url, p.description, p.category_label, p.created_at, u.name AS author_name
                    FROM pins p
                    INNER JOIN users u ON u.id = p.user_id
                    WHERE p.user_id = :user_id
                    ORDER BY p.created_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            $sql = 'SELECT p.id, p.title, p.image_url, p.description, p.created_at, u.name AS author_name
                    FROM pins p
                    INNER JOIN users u ON u.id = p.user_id
                    WHERE p.user_id = :user_id
                    ORDER BY p.created_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    public function countByUser(int $userId): int
    {
        if ($userId <= 0) {
            return 0;
        }

        $sql = 'SELECT COUNT(*) FROM pins WHERE user_id = :user_id';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(['user_id' => $userId]);

        return (int) $stmt->fetchColumn();
    }

    public function createPin(
        int $userId,
        string $title,
        string $imageUrl,
        string $description,
        string $categoryLabel = ''
    ): bool
    {
        if ($userId <= 0 || $title === '' || $imageUrl === '') {
            return false;
        }

        try {
            $this->ensureCategoryLabelColumn();
            $sql = 'INSERT INTO pins (user_id, title, image_url, description, category_label, created_at)
                    VALUES (:user_id, :title, :image_url, :description, :category_label, NOW())';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'user_id' => $userId,
                'title' => $title,
                'image_url' => $imageUrl,
                'description' => $description,
                'category_label' => $this->cleanCategoryLabel($categoryLabel),
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function countAll(?string $keyword = null, ?string $category = null): int
    {
        $keyword = trim((string) $keyword);
        $category = $this->cleanCategoryLabel((string) $category);
        $hasKeyword = $keyword !== '';
        $hasCategory = $category !== '';

        try {
            $conditions = [];
            $params = [];

            if ($hasKeyword) {
                $conditions[] = 'title LIKE :keyword_title';
                $params['keyword_title'] = '%' . $keyword . '%';
            }
            if ($hasCategory) {
                $this->ensureCategoryLabelColumn();
                $conditions[] = 'LOWER(category_label) = :category_label';
                $params['category_label'] = mb_strtolower($category);
            }

            $sql = 'SELECT COUNT(*) AS total FROM pins';
            if ($conditions !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $conditions);
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($row['total'] ?? 0);
        } catch (PDOException $e) {
            if (!$hasKeyword) {
                $stmt = $this->db->query('SELECT COUNT(*) AS total FROM pins');
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                return (int) ($row['total'] ?? 0);
            }

            $sql = 'SELECT COUNT(*) AS total
                    FROM pins
                    WHERE title LIKE :keyword_title';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'keyword_title' => '%' . $keyword . '%',
            ]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return (int) ($row['total'] ?? 0);
        }
    }

    public function feed(int $offset, int $limit, ?string $keyword = null, ?string $category = null): array
    {
        $keyword = trim((string) $keyword);
        $category = $this->cleanCategoryLabel((string) $category);
        $hasKeyword = $keyword !== '';
        $hasCategory = $category !== '';

        try {
            $this->ensureCategoryLabelColumn();
            $where = [];
            $params = [];
            if ($hasKeyword) {
                $where[] = '(p.title LIKE :keyword_title OR p.description LIKE :keyword_desc)';
                $params[':keyword_title'] = '%' . $keyword . '%';
                $params[':keyword_desc'] = '%' . $keyword . '%';
            }
            if ($hasCategory) {
                $where[] = 'LOWER(p.category_label) = :category_label';
                $params[':category_label'] = mb_strtolower($category);
            }

            $sql = 'SELECT p.id,
                           p.title,
                           p.image_url,
                           p.description,
                           p.category_label,
                           p.created_at,
                           u.name AS author_name
                    FROM pins p
                    INNER JOIN users u ON u.id = p.user_id';
            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY p.created_at DESC
                      LIMIT :offset, :limit';

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            // Fallback for pre-migration schema without user/category columns.
            $where = [];
            $params = [];
            if ($hasKeyword) {
                $where[] = '(title LIKE :keyword_title OR description LIKE :keyword_desc)';
                $params[':keyword_title'] = '%' . $keyword . '%';
                $params[':keyword_desc'] = '%' . $keyword . '%';
            }

            $sql = 'SELECT id, title, image_url, description, created_at
                    FROM pins';
            if ($where !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY created_at DESC
                      LIMIT :offset, :limit';
            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value, PDO::PARAM_STR);
            }
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
    }

    public function findById(int $id): ?array
    {
        try {
            $this->ensureCategoryLabelColumn();
            $sql = 'SELECT p.id,
                           p.user_id,
                           p.title,
                           p.image_url,
                           p.description,
                           p.category_label,
                           p.source_link,
                           p.created_at,
                           u.name AS author_name,
                           c.name AS category_name
                    FROM pins p
                    INNER JOIN users u ON u.id = p.user_id
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE p.id = :id
                    LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $pin = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            $sql = 'SELECT id, user_id, title, image_url, description, source_link, created_at FROM pins WHERE id = :id LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            $pin = $stmt->fetch(PDO::FETCH_ASSOC);
        }

        return $pin ?: null;
    }

    public function getLikeCount(int $pinId): int
    {
        try {
            $sql = 'SELECT COUNT(*) AS total FROM likes WHERE pin_id = :pin_id';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['pin_id' => $pinId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return 0;
        }

        return (int) ($row['total'] ?? 0);
    }

    public function hasLikedByUser(int $pinId, int $userId): bool
    {
        try {
            $sql = 'SELECT 1 FROM likes WHERE pin_id = :pin_id AND user_id = :user_id LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pin_id' => $pinId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }

        return (bool) $stmt->fetchColumn();
    }

    public function likePin(int $pinId, int $userId): bool
    {
        try {
            $sql = 'INSERT IGNORE INTO likes (pin_id, user_id, created_at) VALUES (:pin_id, :user_id, NOW())';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'pin_id' => $pinId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function unlikePin(int $pinId, int $userId): bool
    {
        try {
            $sql = 'DELETE FROM likes WHERE pin_id = :pin_id AND user_id = :user_id';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'pin_id' => $pinId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function listComments(int $pinId): array
    {
        try {
            $this->ensureCommentsTable();
            $sql = 'SELECT c.id, c.content, c.created_at, c.user_id, u.name AS user_name
                    FROM comments c
                    INNER JOIN users u ON u.id = c.user_id
                    WHERE c.pin_id = :pin_id
                    ORDER BY c.created_at DESC, c.id DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['pin_id' => $pinId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function addComment(int $pinId, int $userId, string $content): bool
    {
        try {
            $this->ensureCommentsTable();
            $sql = 'INSERT INTO comments (pin_id, user_id, content, created_at)
                    VALUES (:pin_id, :user_id, :content, NOW())';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'pin_id' => $pinId,
                'user_id' => $userId,
                'content' => $content,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    private function ensureCommentsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS comments (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    pin_id INT UNSIGNED NOT NULL,
                    user_id INT UNSIGNED NOT NULL,
                    content TEXT NOT NULL,
                    created_at DATETIME NOT NULL,
                    INDEX idx_comments_pin (pin_id),
                    INDEX idx_comments_user (user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec($sql);
    }

    public function isSavedByUser(int $pinId, int $userId): bool
    {
        try {
            $sql = 'SELECT 1 FROM saved_pins WHERE pin_id = :pin_id AND user_id = :user_id LIMIT 1';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'pin_id' => $pinId,
                'user_id' => $userId,
            ]);
        } catch (PDOException $e) {
            return false;
        }

        return (bool) $stmt->fetchColumn();
    }

    public function savePin(int $pinId, int $userId): bool
    {
        try {
            $sql = 'INSERT IGNORE INTO saved_pins (user_id, pin_id, created_at) VALUES (:user_id, :pin_id, NOW())';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'user_id' => $userId,
                'pin_id' => $pinId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function unsavePin(int $pinId, int $userId): bool
    {
        try {
            $sql = 'DELETE FROM saved_pins WHERE user_id = :user_id AND pin_id = :pin_id';
            $stmt = $this->db->prepare($sql);

            return $stmt->execute([
                'user_id' => $userId,
                'pin_id' => $pinId,
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function getSavedPins(int $userId): array
    {
        try {
            $sql = 'SELECT p.id, p.title, p.image_url, p.description, p.created_at, u.name AS author_name
                    FROM saved_pins sp
                    INNER JOIN pins p ON p.id = sp.pin_id
                    INNER JOIN users u ON u.id = p.user_id
                    WHERE sp.user_id = :user_id
                    ORDER BY sp.created_at DESC';
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $userId]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            return [];
        }
    }

    public function getSavedPinIdsForUser(int $userId, array $pinIds): array
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
            $sql = 'SELECT pin_id
                    FROM saved_pins
                    WHERE user_id = :user_id
                      AND pin_id IN (' . implode(', ', $placeholders) . ')';
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            return [];
        }

        return array_map('intval', $rows);
    }

    public function listSmartCategories(int $limit = 12): array
    {
        $this->ensureCategoryLabelColumn();
        $limit = max(1, min(30, $limit));

        try {
            $sql = 'SELECT category_label
                    FROM pins
                    WHERE category_label IS NOT NULL
                      AND TRIM(category_label) <> ""
                    ORDER BY created_at DESC';
            $stmt = $this->db->query($sql);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];
        } catch (PDOException $e) {
            return [];
        }

        $clusters = [];
        foreach ($rows as $rawLabel) {
            $label = $this->cleanCategoryLabel((string) $rawLabel);
            if ($label === '') {
                continue;
            }

            $matchedKey = null;
            foreach ($clusters as $key => $cluster) {
                if ($this->isSimilarCategory($label, (string) $cluster['label'])) {
                    $matchedKey = $key;
                    break;
                }
            }

            if ($matchedKey !== null) {
                $clusters[$matchedKey]['count']++;
                continue;
            }

            $clusters[] = [
                'label' => $label,
                'count' => 1,
            ];
        }

        usort($clusters, static function (array $a, array $b): int {
            return $b['count'] <=> $a['count'];
        });

        return array_slice(array_map(static function (array $cluster): string {
            return (string) $cluster['label'];
        }, $clusters), 0, $limit);
    }

    private function ensureReportsTable(): void
    {
        $sql = 'CREATE TABLE IF NOT EXISTS pin_reports (
                    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    pin_id INT UNSIGNED NOT NULL,
                    reported_by INT UNSIGNED NULL,
                    reason VARCHAR(255) NULL,
                    status VARCHAR(20) NOT NULL DEFAULT "PENDING",
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_pin_reports_pin (pin_id),
                    INDEX idx_pin_reports_status (status),
                    INDEX idx_pin_reports_reported_by (reported_by)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec($sql);
    }

    private function cleanCategoryLabel(string $label): string
    {
        $label = trim($label);
        if ($label === '') {
            return '';
        }

        $label = preg_replace('/\s+/u', ' ', $label) ?? $label;
        if (mb_strlen($label) > 80) {
            $label = mb_substr($label, 0, 80);
        }

        return $label;
    }

    private function isSimilarCategory(string $left, string $right): bool
    {
        $leftNorm = $this->normalizeCategory($left);
        $rightNorm = $this->normalizeCategory($right);

        if ($leftNorm === '' || $rightNorm === '') {
            return false;
        }
        if ($leftNorm === $rightNorm) {
            return true;
        }
        if (str_contains($leftNorm, $rightNorm) || str_contains($rightNorm, $leftNorm)) {
            return true;
        }

        $distance = levenshtein($leftNorm, $rightNorm);
        if ($distance <= 2) {
            return true;
        }

        similar_text($leftNorm, $rightNorm, $percent);
        return $percent >= 72;
    }

    private function normalizeCategory(string $label): string
    {
        $label = mb_strtolower(trim($label));
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $label);
        if (is_string($ascii) && $ascii !== '') {
            $label = $ascii;
        }
        $label = preg_replace('/[^a-z0-9\s]/', ' ', $label) ?? $label;
        $label = preg_replace('/\s+/', ' ', $label) ?? $label;
        $label = trim($label);

        $synonymMap = [
            'am thuc' => 'an uong',
            'do an' => 'an uong',
            'mon an' => 'an uong',
            'fashion' => 'thoi trang',
            'style' => 'thoi trang',
            'du lich' => 'travel',
            'trip' => 'travel',
            'suc khoe' => 'health',
            'the thao' => 'sport',
            'thu cung' => 'pet',
            'dong vat' => 'pet',
            'nghe thuat' => 'art',
        ];

        return $synonymMap[$label] ?? $label;
    }

    private function ensureCategoryLabelColumn(): void
    {
        try {
            $this->db->exec('ALTER TABLE pins ADD COLUMN category_label VARCHAR(80) NULL');
        } catch (PDOException $e) {
            // Column probably exists or schema incompatible.
        }
    }
}
