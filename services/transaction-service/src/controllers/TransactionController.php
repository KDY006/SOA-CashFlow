<?php
namespace App\Controllers;

use App\Models\Transaction;
use App\Models\Budget;
use App\Models\Category;
use App\Helpers\Response;

class TransactionController {
    private Transaction $transactionModel;
    private Budget $budgetModel;
    private Category $categoryModel;

    public function __construct() {
        $this->transactionModel = new Transaction();
        $this->budgetModel = new Budget();
        $this->categoryModel = new Category();
    }

    /**
     * Lấy user_id từ request
     */
    private function resolveUserId(?array $body = null): int {
        if (!empty($_GET['user_id'])) {
            return (int) $_GET['user_id'];
        }
        if ($body && !empty($body['user_id'])) {
            return (int) $body['user_id'];
        }
        $headerUserId = $_SERVER['HTTP_X_USER_ID'] ?? null;
        if ($headerUserId) {
            return (int) $headerUserId;
        }
        return 1;
    }

    /**
     * GET /api/transactions
     */
    public function index(): void {
        $filters = [
            'user_id'     => $_GET['user_id'] ?? null,
            'type'        => $_GET['type'] ?? null,
            'category_id' => $_GET['category_id'] ?? null,
            'from_date'   => $_GET['from_date'] ?? null,
            'to_date'     => $_GET['to_date'] ?? null,
            'limit'       => $_GET['limit'] ?? 100,
            'offset'      => $_GET['offset'] ?? 0
        ];

        // Nếu có X-User-Id mà không có query user_id thì lấy từ header
        if (empty($filters['user_id']) && !empty($_SERVER['HTTP_X_USER_ID'])) {
            $filters['user_id'] = (int) $_SERVER['HTTP_X_USER_ID'];
        }

        $transactions = $this->transactionModel->getAll($filters);
        Response::success($transactions, 'Lấy danh sách giao dịch thành công');
    }

    /**
     * GET /api/transactions/{id}
     */
    public function show(int $id): void {
        $transaction = $this->transactionModel->getById($id);
        if (!$transaction) {
            Response::error('Không tìm thấy giao dịch với ID này', 404);
        }
        Response::success($transaction, 'Lấy chi tiết giao dịch thành công');
    }

    /**
     * POST /api/transactions
     * Thêm mới giao dịch và tự động kiểm tra vượt hạn mức ngân sách
     */
    public function store(): void {
        $raw = file_get_contents('php://input');
        $body = json_decode($raw, true);

        if (!$body || !is_array($body)) {
            Response::error('Dữ liệu JSON gửi lên không hợp lệ', 400);
        }

        if (!isset($body['amount']) || !is_numeric($body['amount']) || (float)$body['amount'] <= 0) {
            Response::error('Số tiền giao dịch (amount) phải là số dương lớn hơn 0', 400);
        }

        $type = strtoupper($body['type'] ?? 'EXPENSE');
        if (!in_array($type, ['INCOME', 'EXPENSE', 'TRANSFER'])) {
            $type = 'EXPENSE';
        }

        $userId = $this->resolveUserId($body);
        $categoryId = !empty($body['category_id']) ? (int) $body['category_id'] : null;
        $categoryName = $body['category'] ?? null;

        // Nếu có category name nhưng chưa có category_id, tìm thử trong bảng categories
        if ($categoryId === null && !empty($categoryName)) {
            $foundCat = $this->categoryModel->getByName($categoryName);
            if ($foundCat) {
                $categoryId = (int) $foundCat['id'];
            }
        } elseif ($categoryId !== null && empty($categoryName)) {
            $foundCat = $this->categoryModel->getById($categoryId);
            if ($foundCat) {
                $categoryName = $foundCat['name'];
            }
        }

        if (empty($categoryName)) {
            $categoryName = $type === 'INCOME' ? 'Thu nhập khác' : 'Chi tiêu khác';
        }

        $transactionDate = $body['transaction_date'] ?? date('Y-m-d H:i:s');

        // Lưu vào CSDL
        $newId = $this->transactionModel->create([
            'user_id'          => $userId,
            'category_id'      => $categoryId,
            'category'         => $categoryName,
            'amount'           => (float) $body['amount'],
            'type'             => $type,
            'description'      => $body['description'] ?? '',
            'transaction_date' => $transactionDate
        ]);

        $created = $this->transactionModel->getById($newId);

        // LOGIC KIỂM TRA NGÂN SÁCH (BUDGET ALERT) NẾU LÀ GIAO DỊCH CHI TIÊU (EXPENSE)
        $budgetAlert = null;
        if ($type === 'EXPENSE' && $categoryId !== null) {
            $activeBudget = $this->budgetModel->findActiveBudget($userId, $categoryId, $transactionDate);
            if ($activeBudget) {
                $totalSpent = $this->transactionModel->getTotalExpenseByCategory(
                    $userId,
                    $categoryId,
                    $activeBudget['start_date'],
                    $activeBudget['end_date']
                );

                $limit = (float) $activeBudget['amount_limit'];

                if ($totalSpent > $limit) {
                    $budgetAlert = [
                        'status'       => 'EXCEEDED',
                        'category'     => $activeBudget['category_name'],
                        'amount_limit' => $limit,
                        'total_spent'  => $totalSpent,
                        'over_amount'  => round($totalSpent - $limit, 2),
                        'message'      => "CẢNH BÁO: Giao dịch này đã làm vượt ngân sách '{$activeBudget['category_name']}'!"
                    ];
                } elseif ($totalSpent >= ($limit * 0.8)) {
                    $budgetAlert = [
                        'status'       => 'WARNING',
                        'category'     => $activeBudget['category_name'],
                        'amount_limit' => $limit,
                        'total_spent'  => $totalSpent,
                        'remaining'    => round($limit - $totalSpent, 2),
                        'message'      => "CHÚ Ý: Đã sử dụng trên 80% ngân sách '{$activeBudget['category_name']}'!"
                    ];
                }
            }
        }

        $responseData = [
            'transaction' => $created
        ];

        if ($budgetAlert !== null) {
            $responseData['budget_alert'] = $budgetAlert;
        }

        Response::success($responseData, 'Tạo giao dịch thành công', 201);
    }

    /**
     * PUT /api/transactions/{id}
     */
    public function update(int $id): void {
        $transaction = $this->transactionModel->getById($id);
        if (!$transaction) {
            Response::error('Không tìm thấy giao dịch để cập nhật', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body)) {
            Response::error('Dữ liệu cập nhật không hợp lệ', 400);
        }

        $this->transactionModel->update($id, $body);
        $updated = $this->transactionModel->getById($id);
        Response::success($updated, 'Cập nhật giao dịch thành công');
    }

    /**
     * DELETE /api/transactions/{id}
     */
    public function delete(int $id): void {
        $transaction = $this->transactionModel->getById($id);
        if (!$transaction) {
            Response::error('Không tìm thấy giao dịch để xóa', 404);
        }

        $this->transactionModel->delete($id);
        Response::success(null, 'Xóa giao dịch thành công');
    }
}

