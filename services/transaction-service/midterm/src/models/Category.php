<?php
namespace App\Models;

use App\Config\Database;
use PDO;

class Category {
    private PDO $db;

    public function __construct() {
        $this->db = Database::getConnection();
    }

    public function getAll(?string $type = null): array {
        if ($type) {
            $stmt = $this->db->prepare("SELECT * FROM categories WHERE type = :type ORDER BY id ASC");
            $stmt->execute(['type' => strtoupper($type)]);
        } else {
            $stmt = $this->db->query("SELECT * FROM categories ORDER BY id ASC");
        }
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByName(string $name): ?array {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE LOWER(name) = LOWER(:name) LIMIT 1");
        $stmt->execute(['name' => $name]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO categories (name, type, icon)
            VALUES (:name, :type, :icon)
        ");
        $stmt->execute([
            'name' => $data['name'],
            'type' => strtoupper($data['type']),
            'icon' => $data['icon'] ?? 'fa-tag'
        ]);
        return (int) $this->db->lastInsertId();
    }
}

