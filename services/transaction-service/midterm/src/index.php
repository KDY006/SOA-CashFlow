<?php
/**
 * SOA CashFlow - Transaction & Budget Microservice (PHP Native)
 * Báo cáo Giữa kỳ - Kiến trúc Hướng Dịch vụ
 */

// 1. Đăng ký PSR-4 Autoloader thủ công cho PHP Native
spl_autoload_register(function ($class) {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/';
    $len = strlen($prefix);

    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relativeClass = substr($class, $len);
    $pathParts = explode('\\', $relativeClass);
    
    // Thư mục viết thường (config, controllers, models, helpers)
    if (count($pathParts) > 1) {
        $pathParts[0] = strtolower($pathParts[0]);
    }
    
    $file = $baseDir . implode('/', $pathParts) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

use App\Helpers\Response;
use App\Controllers\TransactionController;
use App\Controllers\BudgetController;
use App\Controllers\CategoryController;

// 2. Xử lý CORS Preflight Request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id');
    http_response_code(200);
    exit;
}

try {
    $rawUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $uri = rtrim($rawUri, '/');
    if ($uri === '') {
        $uri = '/';
    }

    // 3. Health Check Endpoints
    if ($uri === '/' || $uri === '/health') {
        Response::success([
            'service' => 'transaction-budget-service',
            'status'  => 'UP',
            'version' => '1.0.0-midterm (PHP Native)',
            'time'    => date('Y-m-d H:i:s')
        ], 'Transaction & Budget Service is running successfully');
    }

    // 4. API Endpoints: CATEGORIES
    if ($uri === '/api/categories') {
        $controller = new CategoryController();
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->store();
        }
    }

    // 5. API Endpoints: BUDGETS
    if ($uri === '/api/budgets/progress' && $method === 'GET') {
        (new BudgetController())->progress();
    }

    if ($uri === '/api/budgets') {
        $controller = new BudgetController();
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->store();
        }
    }

    if (preg_match('#^/api/budgets/(\d+)$#', $uri, $matches)) {
        $budgetId = (int) $matches[1];
        $controller = new BudgetController();
        if ($method === 'GET') {
            $controller->show($budgetId);
        } elseif ($method === 'PUT') {
            $controller->update($budgetId);
        } elseif ($method === 'DELETE') {
            $controller->delete($budgetId);
        }
    }

    // 6. API Endpoints: TRANSACTIONS
    if ($uri === '/api/transactions') {
        $controller = new TransactionController();
        if ($method === 'GET') {
            $controller->index();
        } elseif ($method === 'POST') {
            $controller->store();
        }
    }

    if (preg_match('#^/api/transactions/(\d+)$#', $uri, $matches)) {
        $transId = (int) $matches[1];
        $controller = new TransactionController();
        if ($method === 'GET') {
            $controller->show($transId);
        } elseif ($method === 'PUT') {
            $controller->update($transId);
        } elseif ($method === 'DELETE') {
            $controller->delete($transId);
        }
    }

    // Nếu không khớp route nào
    Response::error("Đường dẫn API không tồn tại: {$method} {$uri}", 404);

} catch (Throwable $e) {
    Response::error('Lỗi hệ thống nội bộ: ' . $e->getMessage(), 500);
}

