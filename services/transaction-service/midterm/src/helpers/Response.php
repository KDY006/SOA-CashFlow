<?php
namespace App\Helpers;

class Response {
    /**
     * Trả về phản hồi dạng JSON với HTTP status code
     */
    public static function json(array $data, int $statusCode = 200): void {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        header('Access-Control-Allow-Origin: *');
        header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id');
        
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        exit;
    }

    /**
     * Phản hồi thành công chuẩn
     */
    public static function success($data = null, string $message = 'Thao tác thành công', int $statusCode = 200): void {
        self::json([
            'success' => true,
            'message' => $message,
            'data'    => $data
        ], $statusCode);
    }

    /**
     * Phản hồi thất bại / lỗi chuẩn
     */
    public static function error(string $message = 'Đã có lỗi xảy ra', int $statusCode = 400, $errors = null): void {
        $payload = [
            'success' => false,
            'message' => $message
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        self::json($payload, $statusCode);
    }
}

