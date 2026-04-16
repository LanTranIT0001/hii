<?php

declare(strict_types=1);

namespace App\Models;

use Core\Model;
use PDO;
use PDOException;

class Pin extends Model
{
    public function listReportedPins(): array
    {
        $this->ensureReportsTable();

        $sql = 'SELECT p.id,
                       p.title,
                       p.image_url,
                       p.description,
                       p.created_at,
                       u.name AS author_name,
                       COUNT(pr.id) AS report_count,
                       MAX(pr.created_at) AS latest_report_at
                FROM pin_reports pr
                INNER JOIN pins p ON p.id = pr.pin_id
                INNER JOIN users u ON u.id = p.user_id
                WHERE pr.status = :status_pending
                GROUP BY p.id, p.title, p.image_url, p.description, p.created_at, u.name
                ORDER BY latest_report_at DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'status_pending' => 'PENDING',
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function deletePinByAdmin(int $pinId): bool
    {
        if ($pinId <= 0) {
            return false;
        }

        try {
            $this->db->beginTransaction();

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
                    // Ignore for older schemas that do not have these tables.
                }
            }

            $stmt = $this->db->prepare('DELETE FROM pin_reports WHERE pin_id = :pin_id');
            $stmt->execute(['pin_id' => $pinId]);

            $stmt = $this->db->prepare('DELETE FROM pins WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $pinId]);
            $deleted = $stmt->rowCount() > 0;

            if ($deleted) {
                $this->db->commit();
                return true;
            }

            $this->db->rollBack();
            return false;
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return false;
        }
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
                    INDEX idx_pin_reports_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
        $this->db->exec($sql);
    }
}
