<?php
namespace App\Helpers;

/**
 * Xác định người dùng từ các header do API Gateway gắn vào sau khi xác thực JWT
 * (X-User-Id, X-User-Role). Cùng quy tắc với integration-service (Go).
 */
class Auth {
    /**
     * user_id của người đang đăng nhập (header X-User-Id), 0 nếu gọi trực tiếp không qua gateway
     */
    public static function headerUserId(): int {
        $value = $_SERVER['HTTP_X_USER_ID'] ?? '';
        return is_numeric($value) && (int) $value > 0 ? (int) $value : 0;
    }

    /**
     * role do gateway giải mã từ JWT rồi gắn vào header X-User-Role
     */
    public static function isAdmin(): bool {
        return strcasecmp(trim($_SERVER['HTTP_X_USER_ROLE'] ?? ''), 'ADMIN') === 0;
    }

    /**
     * Lấy user_id hiệu lực của request:
     *  - Có X-User-Id: dùng header; riêng ADMIN được chỉ định user khác qua ?user_id= hoặc body user_id
     *  - Không có header (gọi service-to-service / test): dùng query rồi body user_id, mặc định là 1
     */
    public static function resolveUserId(?array $body = null): int {
        $requested = 0;
        if (!empty($_GET['user_id']) && (int) $_GET['user_id'] > 0) {
            $requested = (int) $_GET['user_id'];
        } elseif ($body && !empty($body['user_id']) && (int) $body['user_id'] > 0) {
            $requested = (int) $body['user_id'];
        }

        $headerUserId = self::headerUserId();
        if ($headerUserId > 0) {
            return (self::isAdmin() && $requested > 0) ? $requested : $headerUserId;
        }
        return $requested > 0 ? $requested : 1;
    }

    /**
     * Có user đăng nhập mà không phải chủ dữ liệu thì coi như không tồn tại (trả 404 thay vì 403
     * để không lộ ra là id đó có tồn tại). ADMIN được xem tất cả
     */
    public static function forbiddenFor(int $ownerId): bool {
        $uid = self::headerUserId();
        return $uid > 0 && $uid !== $ownerId && !self::isAdmin();
    }
}
