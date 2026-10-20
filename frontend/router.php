<?php
/**
 * Router cho PHP built-in server (php -S 127.0.0.1:3000 -t frontend frontend/router.php).
 * Built-in server chỉ xử lý 1 request/lần và không gửi header cache cho file tĩnh,
 * nên mỗi lần chuyển trang trình duyệt lại tải lại CSS/JS/font/ảnh.
 * Router này phục vụ file tĩnh kèm Cache-Control + ETag (304) để trang sau tải gần như tức thì.
 */
if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF, X-Cashflow-Sid');
    header('Access-Control-Expose-Headers: X-Cashflow-Sid, X-Cashflow-Location');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        return true;
    }
}

$path = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/');
$file = realpath(__DIR__ . $path);

$types = [
    'css' => 'text/css; charset=utf-8', 'js' => 'application/javascript; charset=utf-8',
    'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp',
    'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon',
    'woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'json' => 'application/json',
];
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (!$file || !is_file($file) || !str_starts_with($file, __DIR__) || !isset($types[$ext])) {
    return false; // file PHP / không tồn tại -> để built-in server xử lý như bình thường
}

$mtime = filemtime($file);
$etag = '"' . dechex($mtime) . '-' . dechex(filesize($file)) . '"';

// Thư viện vendor + file có ?v=<mtime> -> cache lâu dài; còn lại cache 1 giờ
$immutable = str_contains($path, '/vendor/') || isset($_GET['v']);
header('Cache-Control: ' . ($immutable ? 'public, max-age=31536000, immutable' : 'public, max-age=3600'));
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');
header('Content-Type: ' . $types[$ext]);

if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    return true;
}
header('Content-Length: ' . filesize($file));
readfile($file);
return true;
