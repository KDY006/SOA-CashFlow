<?php
namespace App\Config;

use PDO;
use PDOException;

class Database {
    private static ?PDO $instance = null;

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $dbName = getenv('DB_NAME') ?: (getenv('DB_DATABASE') ?: 'transaction_db');
            $user = getenv('DB_USER') ?: (getenv('DB_USERNAME') ?: 'root');
            $password = getenv('DB_PASSWORD') !== false ? getenv('DB_PASSWORD') : 'root_password';

            $dsn = "mysql:host={$host};port={$port};dbname={$dbName};charset=utf8mb4";
            
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ];

            try {
                self::$instance = new PDO($dsn, $user, $password, $options);
            } catch (PDOException $e) {
                // Thử fallback sang 127.0.0.1 nếu chạy host local và host gốc 'transaction-db' không tìm thấy
                if ($host !== '127.0.0.1' && $host !== 'localhost') {
                    try {
                        $fallbackDsn = "mysql:host=127.0.0.1;port={$port};dbname={$dbName};charset=utf8mb4";
                        self::$instance = new PDO($fallbackDsn, $user, $password, $options);
                        return self::$instance;
                    } catch (PDOException $ex) {
                        // Bỏ qua lỗi fallback, ném ra lỗi ban đầu
                    }
                }

                http_response_code(500);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode([
                    'success' => false,
                    'message' => 'Lỗi kết nối cơ sở dữ liệu MySQL (transaction_db)',
                    'error'   => $e->getMessage()
                ], JSON_UNESCAPED_UNICODE);
                exit;
            }
        }

        return self::$instance;
    }
}

