<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Budget {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Lấy danh sách ngân sách kèm thông tin danh mục
     */
    public function getAll(?int $userId = null): array {
        $sql = "
            SELECT b.*, c.name AS category_name, c.icon AS category_icon
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if ($userId !== null) {
            $sql .= " AND b.user_id = :user_id";
            $params['user_id'] = $userId;
        }

        $sql .= " ORDER BY b.start_date DESC, b.id DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        foreach ($results as &$item) {
            $item['id'] = (int) $item['id'];
            $item['user_id'] = (int) $item['user_id'];
            $item['category_id'] = (int) $item['category_id'];
            $item['amount_limit'] = (float) $item['amount_limit'];
        }

        return $results;
    }

    /**
     * Lấy thông tin chi tiết một ngân sách
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS category_name, c.icon AS category_icon
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            WHERE b.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        if ($res) {
            $res['id'] = (int) $res['id'];
            $res['user_id'] = (int) $res['user_id'];
            $res['category_id'] = (int) $res['category_id'];
            $res['amount_limit'] = (float) $res['amount_limit'];
            return $res;
        }
        return null;
    }

    /**
     * Tìm ngân sách đang có hiệu lực cho danh mục và thời điểm cụ thể
     */
    public function findActiveBudget(int $userId, int $categoryId, ?string $date = null): ?array {
        $date = $date ? substr($date, 0, 10) : date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT b.*, c.name AS category_name
            FROM budgets b
            JOIN categories c ON b.category_id = c.id
            WHERE b.user_id = :user_id
              AND b.category_id = :category_id
              AND :check_date BETWEEN b.start_date AND b.end_date
            ORDER BY b.id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'user_id'     => $userId,
            'category_id' => $categoryId,
            'check_date'  => $date
        ]);

        $res = $stmt->fetch();
        if ($res) {
            $res['id'] = (int) $res['id'];
            $res['user_id'] = (int) $res['user_id'];
            $res['category_id'] = (int) $res['category_id'];
            $res['amount_limit'] = (float) $res['amount_limit'];
            return $res;
        }
        return null;
    }

    /**
     * Thêm mới thiết lập ngân sách
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO budgets (
                user_id, category_id, amount_limit, period, start_date, end_date
            ) VALUES (
                :user_id, :category_id, :amount_limit, :period, :start_date, :end_date
            )
        ");

        $stmt->execute([
            'user_id'      => (int) $data['user_id'],
            'category_id'  => (int) $data['category_id'],
            'amount_limit' => (float) $data['amount_limit'],
            'period'       => strtoupper($data['period'] ?? 'MONTHLY'),
            'start_date'   => $data['start_date'],
            'end_date'     => $data['end_date']
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Cập nhật ngân sách
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['category_id'])) {
            $fields[] = "category_id = :category_id";
            $params['category_id'] = (int) $data['category_id'];
        }
        if (isset($data['amount_limit'])) {
            $fields[] = "amount_limit = :amount_limit";
            $params['amount_limit'] = (float) $data['amount_limit'];
        }
        if (isset($data['period'])) {
            $fields[] = "period = :period";
            $params['period'] = strtoupper($data['period']);
        }
        if (isset($data['start_date'])) {
            $fields[] = "start_date = :start_date";
            $params['start_date'] = $data['start_date'];
        }
        if (isset($data['end_date'])) {
            $fields[] = "end_date = :end_date";
            $params['end_date'] = $data['end_date'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE budgets SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Xóa ngân sách
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM budgets WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }
}

