<?php
namespace App\Controllers;

use App\Models\Category;
use App\Helpers\Response;

class CategoryController {
    private Category $categoryModel;

    public function __construct() {
        $this->categoryModel = new Category();
    }

    /**
     * GET /api/categories
     */
    public function index(): void {
        $type = $_GET['type'] ?? null;
        $categories = $this->categoryModel->getAll($type);
        Response::success($categories, 'Lấy danh sách danh mục thành công');
    }

    /**
     * POST /api/categories
     */
    public function store(): void {
        $body = json_decode(file_get_contents('php://input'), true);

        if (empty($body['name'])) {
            Response::error('Tên danh mục (name) không được để trống', 400);
        }

        $type = strtoupper($body['type'] ?? 'EXPENSE');
        if (!in_array($type, ['INCOME', 'EXPENSE'])) {
            Response::error('Loại danh mục (type) phải là INCOME hoặc EXPENSE', 400);
        }

        $id = $this->categoryModel->create([
            'name' => trim($body['name']),
            'type' => $type,
            'icon' => $body['icon'] ?? 'fa-tag'
        ]);

        $created = $this->categoryModel->getById($id);
        Response::success($created, 'Tạo danh mục mới thành công', 201);
    }
}

