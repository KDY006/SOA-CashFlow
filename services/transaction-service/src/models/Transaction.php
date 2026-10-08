<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Transaction {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    /**
     * Lấy danh sách giao dịch có hỗ trợ bộ lọc và phân trang
     */
    public function getAll(array $filters = []): array {
        $sql = "
            SELECT t.*, c.name AS category_name_rel, c.icon AS category_icon
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE 1=1
        ";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql .= " AND t.user_id = :user_id";
            $params['user_id'] = (int) $filters['user_id'];
        }

        if (!empty($filters['type'])) {
            $sql .= " AND UPPER(t.type) = :type";
            $params['type'] = strtoupper($filters['type']);
        }

        if (!empty($filters['category_id'])) {
            $sql .= " AND t.category_id = :category_id";
            $params['category_id'] = (int) $filters['category_id'];
        }

        if (!empty($filters['from_date'])) {
            $sql .= " AND t.transaction_date >= :from_date";
            $params['from_date'] = $filters['from_date'];
        }

        if (!empty($filters['to_date'])) {
            $sql .= " AND t.transaction_date <= :to_date";
            $params['to_date'] = $filters['to_date'];
        }

        $sql .= " ORDER BY t.transaction_date DESC, t.id DESC";

        $limit = isset($filters['limit']) ? (int) $filters['limit'] : 100;
        $offset = isset($filters['offset']) ? (int) $filters['offset'] : 0;
        $sql .= " LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll();

        // Ép kiểu các trường số
        foreach ($results as &$item) {
            $item['id'] = (int) $item['id'];
            $item['user_id'] = (int) $item['user_id'];
            $item['amount'] = (float) $item['amount'];
            if ($item['category_id'] !== null) {
                $item['category_id'] = (int) $item['category_id'];
            }
        }

        return $results;
    }

    /**
     * Chi tiết một giao dịch
     */
    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("
            SELECT t.*, c.name AS category_name_rel, c.icon AS category_icon
            FROM transactions t
            LEFT JOIN categories c ON t.category_id = c.id
            WHERE t.id = :id
        ");
        $stmt->execute(['id' => $id]);
        $res = $stmt->fetch();
        if ($res) {
            $res['id'] = (int) $res['id'];
            $res['user_id'] = (int) $res['user_id'];
            $res['amount'] = (float) $res['amount'];
            if ($res['category_id'] !== null) {
                $res['category_id'] = (int) $res['category_id'];
            }
            return $res;
        }
        return null;
    }

    /**
     * Thêm mới giao dịch
     */
    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO transactions (
                user_id, category_id, category, amount, type, description, transaction_date
            ) VALUES (
                :user_id, :category_id, :category, :amount, :type, :description, :transaction_date
            )
        ");

        $stmt->execute([
            'user_id'          => (int) $data['user_id'],
            'category_id'      => !empty($data['category_id']) ? (int) $data['category_id'] : null,
            'category'         => $data['category'] ?? 'Chung',
            'amount'           => (float) $data['amount'],
            'type'             => strtoupper($data['type']),
            'description'      => $data['description'] ?? null,
            'transaction_date' => $data['transaction_date'] ?? date('Y-m-d H:i:s')
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Cập nhật giao dịch
     */
    public function update(int $id, array $data): bool {
        $fields = [];
        $params = ['id' => $id];

        if (isset($data['user_id'])) {
            $fields[] = "user_id = :user_id";
            $params['user_id'] = (int) $data['user_id'];
        }
        if (array_key_exists('category_id', $data)) {
            $fields[] = "category_id = :category_id";
            $params['category_id'] = $data['category_id'] ? (int) $data['category_id'] : null;
        }
        if (isset($data['category'])) {
            $fields[] = "category = :category";
            $params['category'] = $data['category'];
        }
        if (isset($data['amount'])) {
            $fields[] = "amount = :amount";
            $params['amount'] = (float) $data['amount'];
        }
        if (isset($data['type'])) {
            $fields[] = "type = :type";
            $params['type'] = strtoupper($data['type']);
        }
        if (isset($data['description'])) {
            $fields[] = "description = :description";
            $params['description'] = $data['description'];
        }
        if (isset($data['transaction_date'])) {
            $fields[] = "transaction_date = :transaction_date";
            $params['transaction_date'] = $data['transaction_date'];
        }

        if (empty($fields)) {
            return false;
        }

        $sql = "UPDATE transactions SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Xóa giao dịch
     */
    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM transactions WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /**
     * Tính tổng số tiền đã chi (EXPENSE) theo danh mục trong một khoảng thời gian
     */
    public function getTotalExpenseByCategory(int $userId, int $categoryId, string $startDate, string $endDate): float {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(amount), 0) AS total_spent
            FROM transactions
            WHERE user_id = :user_id
              AND category_id = :category_id
              AND UPPER(type) = 'EXPENSE'
              AND transaction_date >= :start_date
              AND transaction_date <= :end_date
        ");

        $stmt->execute([
            'user_id'     => $userId,
            'category_id' => $categoryId,
            'start_date'  => $startDate . ' 00:00:00',
            'end_date'    => $endDate . ' 23:59:59'
        ]);

        $row = $stmt->fetch();
        return (float) ($row['total_spent'] ?? 0);
    }
}

