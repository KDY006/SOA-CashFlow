<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Khởi tạo dữ liệu mẫu chuẩn cho SOA CashFlow (Idempotent - chạy lại không trùng).
     */
    public function run(): void
    {
        $now = now();
        $ym = $now->format('Y-m');
        $prev1 = $now->copy()->subMonth()->format('Y-m');
        $prev2 = $now->copy()->subMonths(2)->format('Y-m');
        $monthStart = $now->copy()->startOfMonth()->toDateString();
        $monthEnd = $now->copy()->endOfMonth()->toDateString();

        // 1. Danh mục chuẩn cho User 1 (admin) và User 2 (member1)
        $categories = [
            ['user_id' => 1, 'name' => 'Lương cố định', 'type' => 'INCOME', 'icon' => 'fa-wallet'],
            ['user_id' => 1, 'name' => 'Thưởng & Đầu tư', 'type' => 'INCOME', 'icon' => 'fa-chart-line'],
            ['user_id' => 1, 'name' => 'Ăn uống & Cà phê', 'type' => 'EXPENSE', 'icon' => 'fa-utensils'],
            ['user_id' => 1, 'name' => 'Tiền nhà & Tiện ích', 'type' => 'EXPENSE', 'icon' => 'fa-home'],
            ['user_id' => 1, 'name' => 'Mua sắm & Giải trí', 'type' => 'EXPENSE', 'icon' => 'fa-shopping-cart'],
            ['user_id' => 2, 'name' => 'Lương cố định', 'type' => 'INCOME', 'icon' => 'fa-wallet'],
            ['user_id' => 2, 'name' => 'Ăn uống & Cà phê', 'type' => 'EXPENSE', 'icon' => 'fa-utensils'],
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            DB::table('categories')->updateOrInsert(
                ['user_id' => $cat['user_id'], 'name' => $cat['name'], 'type' => $cat['type']],
                ['icon' => $cat['icon'], 'created_at' => $now, 'updated_at' => $now]
            );
            $catIds["{$cat['user_id']}:{$cat['name']}"] = DB::table('categories')
                ->where('user_id', $cat['user_id'])
                ->where('name', $cat['name'])
                ->where('type', $cat['type'])
                ->value('id');
        }

        // 2. Ngân sách mẫu cho User 1 (Tháng hiện tại)
        $budgets = [
            ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount_limit' => 5000000.00, 'period' => 'MONTHLY', 'start_date' => $monthStart, 'end_date' => $monthEnd],
            ['user_id' => 1, 'category_id' => $catIds['1:Mua sắm & Giải trí'], 'amount_limit' => 3000000.00, 'period' => 'MONTHLY', 'start_date' => $monthStart, 'end_date' => $monthEnd],
        ];
        foreach ($budgets as $b) {
            DB::table('budgets')->updateOrInsert(
                ['user_id' => $b['user_id'], 'category_id' => $b['category_id'], 'period' => $b['period'], 'start_date' => $b['start_date']],
                ['amount_limit' => $b['amount_limit'], 'end_date' => $b['end_date'], 'created_at' => $now, 'updated_at' => $now]
            );
        }

        // 3. Chỉ seed giao dịch mẫu khi bảng transactions còn trống
        if (DB::table('transactions')->count() === 0) {
            DB::table('transactions')->insert([
                // 2 tháng trước (phục vụ Trends & Forecast)
                ['user_id' => 1, 'category_id' => $catIds['1:Lương cố định'], 'amount' => 25000000.00, 'type' => 'INCOME', 'description' => 'Lương tháng cố định', 'transaction_date' => "$prev2-05 09:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Tiền nhà & Tiện ích'], 'amount' => 6000000.00, 'type' => 'EXPENSE', 'description' => 'Thuê căn hộ & điện nước', 'transaction_date' => "$prev2-06 10:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount' => 3200000.00, 'type' => 'EXPENSE', 'description' => 'Chi tiêu ăn uống trong tháng', 'transaction_date' => "$prev2-18 12:30:00", 'created_at' => $now, 'updated_at' => $now],
                // Tháng trước
                ['user_id' => 1, 'category_id' => $catIds['1:Lương cố định'], 'amount' => 25000000.00, 'type' => 'INCOME', 'description' => 'Lương tháng cố định', 'transaction_date' => "$prev1-05 09:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Tiền nhà & Tiện ích'], 'amount' => 6200000.00, 'type' => 'EXPENSE', 'description' => 'Thuê căn hộ & điện nước', 'transaction_date' => "$prev1-06 10:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount' => 3800000.00, 'type' => 'EXPENSE', 'description' => 'Ăn uống & gặp đối tác', 'transaction_date' => "$prev1-20 19:00:00", 'created_at' => $now, 'updated_at' => $now],
                // Tháng hiện tại (có đủ mẫu cho Anomaly detection & Budget progress)
                ['user_id' => 1, 'category_id' => $catIds['1:Lương cố định'], 'amount' => 26000000.00, 'type' => 'INCOME', 'description' => 'Lương tháng hiện tại', 'transaction_date' => "$ym-01 08:30:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Thưởng & Đầu tư'], 'amount' => 4500000.00, 'type' => 'INCOME', 'description' => 'Cổ tức đầu tư quý', 'transaction_date' => "$ym-03 14:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount' => 150000.00, 'type' => 'EXPENSE', 'description' => 'Cà phê sáng & ăn trưa', 'transaction_date' => "$ym-04 12:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount' => 200000.00, 'type' => 'EXPENSE', 'description' => 'Ăn tối cùng nhóm', 'transaction_date' => "$ym-06 19:30:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount' => 180000.00, 'type' => 'EXPENSE', 'description' => 'Siêu thị thực phẩm', 'transaction_date' => "$ym-08 17:15:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Ăn uống & Cà phê'], 'amount' => 3800000.00, 'type' => 'EXPENSE', 'description' => 'Tiệc liên hoan lớn (mẫu phát hiện chi tiêu bất thường)', 'transaction_date' => "$ym-10 20:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 1, 'category_id' => $catIds['1:Tiền nhà & Tiện ích'], 'amount' => 6100000.00, 'type' => 'EXPENSE', 'description' => 'Tiền nhà & internet tháng hiện tại', 'transaction_date' => "$ym-05 11:00:00", 'created_at' => $now, 'updated_at' => $now],
                // User 2
                ['user_id' => 2, 'category_id' => $catIds['2:Lương cố định'], 'amount' => 18000000.00, 'type' => 'INCOME', 'description' => 'Lương tháng user member1', 'transaction_date' => "$ym-02 09:00:00", 'created_at' => $now, 'updated_at' => $now],
                ['user_id' => 2, 'category_id' => $catIds['2:Ăn uống & Cà phê'], 'amount' => 650000.00, 'type' => 'EXPENSE', 'description' => 'Ăn uống tuần đầu', 'transaction_date' => "$ym-05 18:00:00", 'created_at' => $now, 'updated_at' => $now],
            ]);
        }
    }
}
