<?php
namespace App\Controllers;

use App\Models\Budget;
use App\Models\Transaction;
use App\Helpers\Response;

class BudgetController {
    private Budget $budgetModel;
    private Transaction $transactionModel;

    public function __construct() {
        $this->budgetModel = new Budget();
        $this->transactionModel = new Transaction();
    }

    /**
     * Lấy user_id từ query param, request body, hoặc header X-User-Id (mặc định là 1)
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
     * GET /api/budgets
     */
    public function index(): void {
        $userId = $this->resolveUserId();
        $budgets = $this->budgetModel->getAll($userId);
        Response::success($budgets, 'Lấy danh sách ngân sách thành công');
    }

    /**
     * GET /api/budgets/progress
     * Tính toán tiến độ sử dụng ngân sách & tỷ lệ chi tiêu
     */
    public function progress(): void {
        $userId = $this->resolveUserId();
        $budgets = $this->budgetModel->getAll($userId);

        $report = [];
        foreach ($budgets as $budget) {
            $spent = $this->transactionModel->getTotalExpenseByCategory(
                $userId,
                $budget['category_id'],
                $budget['start_date'],
                $budget['end_date']
            );

            $limit = (float) $budget['amount_limit'];
            $remaining = max(0, $limit - $spent);
            $percentage = $limit > 0 ? round(($spent / $limit) * 100, 2) : 0;

            $status = 'SAFE';
            if ($percentage >= 100) {
                $status = 'EXCEEDED';
            } elseif ($percentage >= 80) {
                $status = 'WARNING';
            }

            $report[] = [
                'budget_id'     => $budget['id'],
                'category_id'   => $budget['category_id'],
                'category_name' => $budget['category_name'],
                'category_icon' => $budget['category_icon'],
                'amount_limit'  => $limit,
                'spent'         => $spent,
                'remaining'     => $remaining,
                'percentage'    => $percentage,
                'status'        => $status,
                'period'        => $budget['period'],
                'start_date'    => $budget['start_date'],
                'end_date'      => $budget['end_date']
            ];
        }

        Response::success($report, 'Lấy tiến độ sử dụng ngân sách thành công');
    }

    /**
     * GET /api/budgets/{id}
     */
    public function show(int $id): void {
        $budget = $this->budgetModel->getById($id);
        if (!$budget) {
            Response::error('Không tìm thấy ngân sách với ID này', 404);
        }
        Response::success($budget, 'Lấy chi tiết ngân sách thành công');
    }

    /**
     * POST /api/budgets
     */
    public function store(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        if (empty($body['category_id']) || empty($body['amount_limit'])) {
            Response::error('Thiếu thông tin bắt buộc: category_id và amount_limit', 400);
        }

        if (empty($body['start_date']) || empty($body['end_date'])) {
            // Mặc định là tháng hiện tại
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
        } else {
            $startDate = $body['start_date'];
            $endDate = $body['end_date'];
        }

        $userId = $this->resolveUserId($body);

        $id = $this->budgetModel->create([
            'user_id'      => $userId,
            'category_id'  => (int) $body['category_id'],
            'amount_limit' => (float) $body['amount_limit'],
            'period'       => $body['period'] ?? 'MONTHLY',
            'start_date'   => $startDate,
            'end_date'     => $endDate
        ]);

        $created = $this->budgetModel->getById($id);
        Response::success($created, 'Thiết lập ngân sách thành công', 201);
    }

    /**
     * PUT /api/budgets/{id}
     */
    public function update(int $id): void {
        $budget = $this->budgetModel->getById($id);
        if (!$budget) {
            Response::error('Không tìm thấy ngân sách để cập nhật', 404);
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (empty($body)) {
            Response::error('Dữ liệu cập nhật không hợp lệ', 400);
        }

        $this->budgetModel->update($id, $body);
        $updated = $this->budgetModel->getById($id);
        Response::success($updated, 'Cập nhật ngân sách thành công');
    }

    /**
     * DELETE /api/budgets/{id}
     */
    public function delete(int $id): void {
        $budget = $this->budgetModel->getById($id);
        if (!$budget) {
            Response::error('Không tìm thấy ngân sách để xóa', 404);
        }

        $this->budgetModel->delete($id);
        Response::success(null, 'Xóa ngân sách thành công');
    }
}

