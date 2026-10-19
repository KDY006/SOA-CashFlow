<?php
/**
 * SOA CashFlow - Web Frontend (PHP)
 * Lớp kết nối API Gateway + helper dùng chung cho mọi trang.
 * Frontend KHÔNG truy cập DB, mọi dữ liệu đi qua Gateway (:8080).
 */

if (!empty($_SERVER['HTTP_ORIGIN'])) {
    header('Access-Control-Allow-Origin: ' . $_SERVER['HTTP_ORIGIN']);
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, PATCH, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-CSRF, X-Cashflow-Sid');
    header('Access-Control-Expose-Headers: X-Cashflow-Sid, X-Cashflow-Location');
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
        http_response_code(204);
        exit;
    }
}

if (!empty($_SERVER['HTTP_X_CASHFLOW_SID']) && preg_match('/^[a-zA-Z0-9,-]{16,64}$/', $_SERVER['HTTP_X_CASHFLOW_SID'])) {
    session_id($_SERVER['HTTP_X_CASHFLOW_SID']);
}
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
header('X-Cashflow-Sid: ' . session_id());
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once __DIR__ . '/bank_logos.php';

// Dùng 127.0.0.1 thay vì "localhost": trên Windows, localhost thử IPv6 (::1) trước
// rồi mới quay về IPv4 -> mất ~200ms cho MỖI lần gọi API.
define('GATEWAY_URL', rtrim(getenv('GATEWAY_URL') ?: 'http://127.0.0.1:8080', '/'));
// SSE realtime: EventSource không gửi được header Authorization nên kết nối thẳng Notification Service
define('NOTIFICATION_SSE_URL', rtrim(getenv('NOTIFICATION_SSE_URL') ?: 'http://localhost:8086', '/'));

/* ------------------------------------------------------------------ */
/*  API CLIENT                                                         */
/* ------------------------------------------------------------------ */

/** Tạo curl handle cho 1 request tới Gateway */
function apiHandle(string $method, string $path, $payload = null)
{
    $ch = curl_init(GATEWAY_URL . $path);
    $headers = ['Accept: application/json', 'Content-Type: application/json', 'Expect:'];
    if (!empty($_SESSION['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_IPRESOLVE      => CURL_IPRESOLVE_V4,
        CURLOPT_TCP_NODELAY    => true,
    ]);
    if ($payload !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    }
    return $ch;
}

/** Chuyển kết quả curl thành mảng chuẩn */
function apiResult($raw, int $status, string $curlErr): array
{
    if ($raw === false || ($status === 0 && $curlErr !== '')) {
        return ['status' => 0, 'ok' => false, 'body' => null,
                'error' => 'Không kết nối được API Gateway (' . GATEWAY_URL . '): ' . $curlErr];
    }
    $body = json_decode($raw, true);
    $ok = $status >= 200 && $status < 300;
    return [
        'status' => $status,
        'ok'     => $ok,
        'body'   => $body ?? $raw,
        'error'  => $ok ? null : apiMessage($body, $status),
    ];
}

/** Ghi nhận thay đổi thông báo -> xoá cache badge chuông */
function apiTouch(string $method, string $path): void
{
    if (strtoupper($method) !== 'GET' && str_starts_with($path, '/api/notifications')) {
        unset($_SESSION['_unread']);
    }
}

/**
 * Gọi API qua Gateway.
 * @return array{status:int, ok:bool, body:mixed, error:?string}
 */
function api(string $method, string $path, $payload = null, bool $retry = true): array
{
    $ch = apiHandle($method, $path, $payload);
    $raw = curl_exec($ch);
    $res = apiResult($raw, (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), curl_error($ch));
    curl_close($ch);
    apiTouch($method, $path);

    // Access token hết hạn -> thử refresh 1 lần rồi gọi lại
    if ($res['status'] === 401 && $retry && !str_starts_with($path, '/api/auth/')) {
        if (refreshAccessToken()) {
            return api($method, $path, $payload, false);
        }
        sessionExpired();
    }
    return $res;
}

/**
 * Gọi NHIỀU API song song (curl_multi) - thời gian tải trang = request chậm nhất,
 * thay vì cộng dồn từng request.
 * @param array<string, array{0:string,1:string,2?:mixed}> $calls  key => [method, path, payload?]
 * @return array<string, array>
 */
function apiAll(array $calls, bool $retry = true): array
{
    $mh = curl_multi_init();
    $handles = [];
    foreach ($calls as $key => $c) {
        $handles[$key] = apiHandle($c[0], $c[1], $c[2] ?? null);
        curl_multi_add_handle($mh, $handles[$key]);
    }
    do {
        $st = curl_multi_exec($mh, $running);
        if ($running) {
            curl_multi_select($mh, 0.5);
        }
    } while ($running && $st === CURLM_OK);

    $out = [];
    $expired = [];
    foreach ($handles as $key => $ch) {
        $out[$key] = apiResult(curl_multi_getcontent($ch), (int) curl_getinfo($ch, CURLINFO_HTTP_CODE), curl_error($ch));
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);
        if ($out[$key]['status'] === 401 && !str_starts_with($calls[$key][1], '/api/auth/')) {
            $expired[$key] = $calls[$key];
        }
    }
    curl_multi_close($mh);

    if ($expired && $retry) {
        if (refreshAccessToken()) {
            return array_replace($out, apiAll($expired, false));
        }
        sessionExpired();
    }
    return $out;
}

function refreshAccessToken(): bool
{
    if (empty($_SESSION['refresh_token'])) {
        return false;
    }
    $res = api('POST', '/api/auth/refresh', ['refreshToken' => $_SESSION['refresh_token']], false);
    if ($res['ok'] && !empty($res['body']['accessToken'])) {
        $_SESSION['access_token'] = $res['body']['accessToken'];
        if (!empty($res['body']['refreshToken'])) {
            $_SESSION['refresh_token'] = $res['body']['refreshToken'];
        }
        return true;
    }
    return false;
}

/** Phiên hết hạn (refresh token cũng hết hạn) -> về trang đăng nhập thay vì hiện lỗi */
function sessionExpired(): void
{
    if (empty($_SESSION['access_token'])) {
        return; // đang ở trang login/register
    }
    unset($_SESSION['access_token'], $_SESSION['refresh_token'], $_SESSION['user'], $_SESSION['_unread'], $_SESSION['_cats']);
    if (defined('CASHFLOW_AJAX')) {
        return; // api.php tự trả 401 JSON
    }
    if (!headers_sent()) {
        redirect('login.php?expired=1');
    } else {
        echo '<script>location.replace("login.php?expired=1")</script>';
    }
    exit;
}

/** Số thông báo chưa đọc cho badge chuông - cache 30s, SSE cập nhật realtime phía trình duyệt */
function unreadCount(): int
{
    $c = $_SESSION['_unread'] ?? null;
    if ($c && $c['at'] > time() - 30) {
        return $c['n'];
    }
    $r = api('GET', '/api/notifications?limit=1&user_id=' . uid());
    $n = $r['ok'] ? (int) ($r['body']['unreadCount'] ?? 0) : 0;
    $_SESSION['_unread'] = ['at' => time(), 'n' => $n];
    return $n;
}
/** Lấy phần dữ liệu chính, vì mỗi service trả về một kiểu khác nhau */
function apiData(array $res, $default = [])
{
    if (!$res['ok']) {
        return $default;
    }
    $b = $res['body'];
    if (is_array($b) && array_key_exists('data', $b)) {
        return $b['data'] ?? $default;
    }
    return $b ?? $default;
}

function apiMessage($body, int $status): string
{
    if (is_array($body)) {
        foreach (['message', 'error'] as $k) {
            if (!empty($body[$k]) && is_string($body[$k])) {
                return $body[$k];
            }
        }
    }
    return "Yêu cầu thất bại (HTTP $status)";
}

/** Gom lỗi của nhiều lần gọi API để hiện 1 banner */
function collectErrors(array ...$responses): array
{
    $errs = [];
    foreach ($responses as $r) {
        if (!$r['ok'] && $r['status'] !== 404) {
            $errs[] = $r['error'];
        }
    }
    return array_values(array_unique($errs));
}

/* ------------------------------------------------------------------ */
/*  AUTH / SESSION                                                     */
/* ------------------------------------------------------------------ */

function requireLogin(): void
{
    if ((empty($_SESSION['access_token']) || empty($_SESSION['user'])) && !empty($_SERVER['HTTP_X_CASHFLOW_SID']) && empty($_SESSION['_logged_out'])) {
        // Tự động khởi tạo phiên demo khi gọi từ Surge lần đầu nếu chưa bấm Đăng xuất
        $res = api('POST', '/api/auth/login', ['username' => 'admin', 'password' => 'password123'], false);
        if ($res['ok'] && !empty($res['body']['accessToken'])) {
            $_SESSION['access_token'] = $res['body']['accessToken'];
            $_SESSION['refresh_token'] = $res['body']['refreshToken'] ?? null;
            $_SESSION['user'] = $res['body']['user'] ?? ['username' => 'admin'];
        }
    }
    if (empty($_SESSION['access_token']) || empty($_SESSION['user'])) {
        redirect('login.php');
    }
}

function currentUser(): array
{
    return $_SESSION['user'] ?? [];
}

function uid(): int
{
    return (int) (currentUser()['id'] ?? 1);
}

/* ------------------------------------------------------------------ */
/*  FLASH + CSRF                                                       */
/* ------------------------------------------------------------------ */

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function takeFlashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function csrfField(): string
{
    return '<input type="hidden" name="_csrf" value="' . csrfToken() . '">';
}

function verifyCsrf(): void
{
    if (!empty($_SERVER['HTTP_X_CASHFLOW_SID'])) {
        return;
    }
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['_csrf'] ?? '')) {
        flash('danger', 'Phiên làm việc đã hết hạn, vui lòng thử lại.');
        redirect($_SERVER['PHP_SELF']);
    }
}

function redirect(string $to): void
{
    if (!empty($_SERVER['HTTP_X_CASHFLOW_SID'])) {
        header('X-Cashflow-Sid: ' . session_id());
        header('X-Cashflow-Location: ' . $to);
        http_response_code(200);
        exit;
    }
    header('Location: ' . $to);
    exit;
}

/* ------------------------------------------------------------------ */
/*  FORMAT HELPERS                                                     */
/* ------------------------------------------------------------------ */

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function vnd($amount, bool $sign = false): string
{
    $n = (float) $amount;
    $s = number_format(abs($n), 0, ',', '.') . ' ₫';
    if ($sign) {
        return ($n >= 0 ? '+' : '−') . $s;
    }
    return ($n < 0 ? '−' : '') . $s;
}

/**
 * Số tiền dạng HTML để hiển thị: dấu, phần số và ký hiệu ₫ tách riêng để style
 * (₫ nhỏ, nâng lên, nhạt hơn). Dùng vnd() cho text thuần (flash, title, thẻ ngân hàng).
 */
function money($amount, bool $sign = false): string
{
    $n = (float) $amount;
    $prefix = $sign ? ($n >= 0 ? '+' : '−') : ($n < 0 ? '−' : '');
    return '<span class="amt">'
        . ($prefix !== '' ? '<span class="amt-sign">' . $prefix . '</span>' : '')
        . '<span class="amt-num">' . number_format(abs($n), 0, ',', '.') . '</span>'
        . '<span class="amt-cur">₫</span></span>';
}

/** Phần trăm dạng HTML, dấu phẩy thập phân kiểu Việt Nam */
function pct($value, int $decimals = 1): string
{
    $v = (float) $value;
    $d = abs($v - round($v)) < 0.05 ? 0 : $decimals;
    return '<span class="amt"><span class="amt-num">' . number_format($v, $d, ',', '.') . '</span><span class="amt-unit">%</span></span>';
}

function shortVnd($amount): string
{
    $n = (float) $amount;
    $a = abs($n);
    if ($a >= 1e9) return round($n / 1e9, 1) . ' tỷ';
    if ($a >= 1e6) return round($n / 1e6, 1) . ' tr';
    if ($a >= 1e3) return round($n / 1e3) . 'k';
    return (string) round($n);
}

function dateVN(?string $dt, bool $withTime = false): string
{
    if (!$dt) return '—';
    $t = strtotime($dt);
    if (!$t) return e($dt);
    return date($withTime ? 'd/m/Y H:i' : 'd/m/Y', $t);
}

function timeAgo(?string $dt): string
{
    $t = $dt ? strtotime($dt) : false;
    if (!$t) return '';
    $d = time() - $t;
    if ($d < 60) return 'vừa xong';
    if ($d < 3600) return floor($d / 60) . ' phút trước';
    if ($d < 86400) return floor($d / 3600) . ' giờ trước';
    if ($d < 86400 * 7) return floor($d / 86400) . ' ngày trước';
    return date('d/m/Y', $t);
}

function initials(string $name): string
{
    $parts = preg_split('/\s+/u', trim($name)) ?: [];
    $first = mb_substr($parts[0] ?? 'U', 0, 1);
    $last = count($parts) > 1 ? mb_substr(end($parts), 0, 1) : '';
    return mb_strtoupper($first . $last);
}

/** Map icon FontAwesome lưu trong DB (vd 'fa-utensils') -> class đầy đủ */
function catIcon(?string $icon, string $type = 'EXPENSE'): string
{
    $icon = trim((string) $icon);
    if ($icon === '') {
        $icon = $type === 'INCOME' ? 'fa-arrow-trend-up' : 'fa-tag';
    }
    if ($icon === 'fa-home') $icon = 'fa-house';
    if ($icon === 'fa-shopping-cart') $icon = 'fa-cart-shopping';
    return 'fa-solid ' . $icon;
}

/** Màu ổn định cho từng danh mục */
function catColor(string $name): string
{
    $palette = ['#0066cc', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', '#06b6d4', '#ec4899', '#14b8a6', '#f97316', '#6366f1'];
    return $palette[abs(crc32($name)) % count($palette)];
}

function bankName(string $code): string
{
    $map = [
        'VCB' => 'Vietcombank', 'TCB' => 'Techcombank', 'BIDV' => 'BIDV', 'MB' => 'MB Bank',
        'ACB' => 'ACB', 'VPB' => 'VPBank', 'TPB' => 'TPBank', 'MOMO' => 'Ví MoMo',
        'ZALOPAY' => 'ZaloPay', 'VTB' => 'VietinBank', 'AGR' => 'Agribank', 'STB' => 'Sacombank',
    ];
    return $map[strtoupper($code)] ?? strtoupper($code);
}

function maskAccount(string $no): string
{
    $len = strlen($no);
    if ($len <= 4) return $no;
    return str_repeat('•', max(0, $len - 4)) . substr($no, -4);
}

function formatCardNumber(string $no): string
{
    $clean = trim($no);
    $last4 = substr($clean, -4);
    return '•••• •••• ' . ($last4 !== '' ? $last4 : '8888');
}

