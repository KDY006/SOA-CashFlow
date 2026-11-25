<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;

class TransactionController extends CashflowController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['INCOME', 'EXPENSE'])],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'from_date' => ['sometimes', 'date'],
            'to_date' => ['sometimes', 'date', ...($request->has('from_date') ? ['after_or_equal:from_date'] : [])],
            'limit' => ['sometimes', 'integer', 'between:1,500'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);

        $query = DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $this->userId($request))
            ->select('transactions.*', 'categories.name as category');

        if (isset($data['type'])) {
            $query->where('transactions.type', $data['type']);
        }
        if (isset($data['category_id'])) {
            $query->where('transactions.category_id', $data['category_id']);
        }
        if (isset($data['from_date'])) {
            $from = strlen($data['from_date']) === 10 ? $data['from_date'].' 00:00:00' : $data['from_date'];
            $query->where('transactions.transaction_date', '>=', $from);
        }
        if (isset($data['to_date'])) {
            $to = strlen($data['to_date']) === 10 ? $data['to_date'].' 23:59:59' : $data['to_date'];
            $query->where('transactions.transaction_date', '<=', $to);
        }

        return $this->ok(
            $query->orderByDesc('transactions.transaction_date')
                ->orderByDesc('transactions.id')
                ->limit($data['limit'] ?? 100)
                ->offset($data['offset'] ?? 0)
                ->get()
        );
    }

    public function show(Request $request, int $transaction)
    {
        $record = DB::table('transactions')
            ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
            ->where('transactions.user_id', $this->userId($request))
            ->where('transactions.id', $transaction)
            ->select('transactions.*', 'categories.name as category')
            ->first() ?? abort(404);

        return $this->ok($record);
    }

    public function totals(Request $request): array
    {
        $row = $this->owned('transactions', $request)->selectRaw("COALESCE(SUM(CASE WHEN type = 'INCOME' THEN amount ELSE 0 END), 0) AS income, COALESCE(SUM(CASE WHEN type = 'EXPENSE' THEN amount ELSE 0 END), 0) AS expense")->first();
        // Fixed-point arithmetic: no floating point accumulation of money.
        $income = bcadd((string) $row->income, '0', 2);
        $expense = bcadd((string) $row->expense, '0', 2);

        return ['income' => $income, 'expense' => $expense, 'balance' => bcsub($income, $expense, 2)];
    }

    public function balance(Request $request)
    {
        return $this->ok($this->totals($request));
    }

    private function save(Request $request, ?int $id = null)
    {
        $old = $id ? $this->record('transactions', $request, $id) : null;
        $data = $request->validate([
            'category_id' => ['required_without:category', 'nullable', 'integer', 'min:1'],
            'category' => ['sometimes', 'nullable', 'string', 'max:100'],
            'amount' => $this->moneyRule(),
            'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'transaction_date' => ['sometimes', 'date_format:Y-m-d H:i:s'],
        ]);

        $result = DB::transaction(function () use ($request, $id, $old, $data) {
            $userId = $this->userId($request);
            $categoryId = $data['category_id'] ?? null;
            $categoryName = isset($data['category']) ? trim((string) $data['category']) : '';

            if ($categoryId !== null) {
                $this->owned('categories', $request)->where('id', $categoryId)->lockForUpdate()->first();
                $this->category($request, (int) $categoryId, $data['type']);
            } else {
                // Hỗ trợ Integration Service gửi tên category thay vì category_id
                $catName = $categoryName !== '' ? $categoryName : ($data['type'] === 'INCOME' ? 'Thu nhập khác' : 'Chi tiêu khác');
                $existingCat = $this->owned('categories', $request)
                    ->where('name', $catName)
                    ->where('type', $data['type'])
                    ->lockForUpdate()
                    ->first();

                if ($existingCat) {
                    $categoryId = (int) $existingCat->id;
                } else {
                    $categoryId = (int) DB::table('categories')->insertGetId([
                        'user_id' => $userId,
                        'name' => $catName,
                        'type' => $data['type'],
                        'icon' => $data['type'] === 'INCOME' ? 'fa-wallet' : 'fa-receipt',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $payload = [
                'category_id' => $categoryId,
                'amount' => $data['amount'],
                'type' => $data['type'],
                'description' => $data['description'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? $old?->transaction_date ?? now()->format('Y-m-d H:i:s'),
            ];

            if ($id) {
                $this->owned('transactions', $request)->where('id', $id)->update($payload + ['updated_at' => now()]);
            } else {
                $id = DB::table('transactions')->insertGetId($payload + ['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            }

            $saved = DB::table('transactions')
                ->leftJoin('categories', 'categories.id', '=', 'transactions.category_id')
                ->where('transactions.id', $id)
                ->select('transactions.*', 'categories.name as category')
                ->first();

            $alerts = (new BudgetController)->report($request);
            $matchedAlert = null;
            foreach ($alerts as $a) {
                if ((int) ($a['category_id'] ?? 0) === (int) $categoryId && in_array($a['status'] ?? '', ['EXCEEDED', 'WARNING'], true)) {
                    $matchedAlert = $a + [
                        'message' => ($a['status'] === 'EXCEEDED')
                            ? "CẢNH BÁO: Chi tiêu đã vượt hạn mức ngân sách ({$a['spent']} / {$a['amount_limit']} VND)!"
                            : "CHÚ Ý: Đã sử dụng {$a['percentage']}% hạn mức ngân sách!",
                    ];
                    break;
                }
            }

            return [
                'status' => $old ? 200 : 201,
                'user_id' => $userId,
                'body' => [
                    'transaction' => $saved,
                    'balance' => $this->totals($request),
                    'budget_alerts' => $alerts,
                    'budget_alert' => $matchedAlert,
                ],
            ];
        });

        // Gửi cảnh báo không đồng bộ tới Notification Service nếu vượt/chạm ngưỡng ngân sách
        $alert = $result['body']['budget_alert'] ?? null;
        $notifUrl = env('NOTIFICATION_SERVICE_URL');
        if ($alert && $notifUrl && ! app()->runningUnitTests()) {
            try {
                Http::timeout(2)->post(rtrim($notifUrl, '/').'/api/notifications/send', [
                    'userId' => $result['user_id'],
                    'type' => 'BUDGET_ALERT',
                    'title' => $alert['status'] === 'EXCEEDED' ? 'Cảnh báo vượt hạn mức ngân sách' : 'Chú ý sắp chạm ngưỡng ngân sách',
                    'message' => $alert['message'],
                    'metadata' => $alert,
                ]);
            } catch (\Throwable) {
                // Không làm gián đoạn giao dịch nếu Notification Service tạm thời không khả dụng
            }
        }

        return $this->ok($result['body'], $result['status']);
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $transaction)
    {
        return $this->save($request, $transaction);
    }

    public function destroy(Request $request, int $transaction)
    {
        return DB::transaction(function () use ($request, $transaction) {
            $this->record('transactions', $request, $transaction);
            $this->owned('transactions', $request)->where('id', $transaction)->delete();

            return $this->ok(['balance' => $this->totals($request), 'budget_alerts' => (new BudgetController)->report($request)]);
        });
    }
}
