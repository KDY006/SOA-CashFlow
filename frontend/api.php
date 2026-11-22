<?php
/**
 * Proxy nhỏ cho các thao tác AJAX (máy tính khoản vay, đồng bộ tỷ giá, đánh dấu đã đọc...).
 * JWT nằm trong session phía server, trình duyệt không bao giờ thấy token.
 */
define('CASHFLOW_AJAX', true);
require_once __DIR__ . '/includes/config.php';

header('Content-Type: application/json; charset=utf-8');

if (empty($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn']);
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD']);
$path = (string) ($_GET['path'] ?? '');

// Chỉ cho phép đi tới các API nghiệp vụ qua Gateway
if (!preg_match('#^/api/[a-z0-9\-/_]+(\?[^\s]*)?$#i', $path) || str_contains($path, '..')) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Đường dẫn API không hợp lệ']);
    exit;
}

if ($method !== 'GET' && !hash_equals($_SESSION['csrf'] ?? '', $_SERVER['HTTP_X_CSRF'] ?? '')) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'CSRF token không hợp lệ, hãy tải lại trang']);
    exit;
}

$body = null;
if (in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
    $raw = file_get_contents('php://input');
    $body = $raw !== '' ? json_decode($raw, true) : [];
}

$res = api($method, $path, $body);
if (empty($_SESSION['access_token'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Phiên đăng nhập đã hết hạn, vui lòng đăng nhập lại', 'relogin' => true]);
    exit;
}
http_response_code($res['status'] ?: 502);
echo is_array($res['body'])
    ? json_encode($res['body'], JSON_UNESCAPED_UNICODE)
    : json_encode(['success' => false, 'message' => $res['error'] ?? 'Gateway không phản hồi']);
