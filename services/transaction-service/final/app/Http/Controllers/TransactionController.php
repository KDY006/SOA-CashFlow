<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class TransactionController extends CashflowController
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => ['sometimes', Rule::in(['INCOME', 'EXPENSE'])],
            'category_id' => ['sometimes', 'integer', 'min:1'],
            'from_date' => ['sometimes', 'date_format:Y-m-d'],
            'to_date' => ['sometimes', 'date_format:Y-m-d', ...($request->has('from_date') ? ['after_or_equal:from_date'] : [])],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
            'offset' => ['sometimes', 'integer', 'min:0'],
        ]);
        $query = $this->owned('transactions', $request);
        foreach (['type', 'category_id'] as $field) {
            if (isset($data[$field])) {
                $query->where($field, $data[$field]);
            }
        }
        if (isset($data['from_date'])) {
            $query->where('transaction_date', '>=', $data['from_date'].' 00:00:00');
        }
        if (isset($data['to_date'])) {
            $query->where('transaction_date', '<=', $data['to_date'].' 23:59:59');
        }

        return $this->ok($query->orderByDesc('transaction_date')->orderByDesc('id')->limit($data['limit'] ?? 100)->offset($data['offset'] ?? 0)->get());
    }

    public function show(Request $request, int $transaction)
    {
        return $this->ok($this->record('transactions', $request, $transaction));
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
            'category_id' => ['required', 'integer', 'min:1'],
            'amount' => $this->moneyRule(),
            'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
            'description' => ['nullable', 'string', 'max:5000'],
            'transaction_date' => ['sometimes', 'date_format:Y-m-d H:i:s'],
        ]);

        return DB::transaction(function () use ($request, $id, $old, $data) {
            $this->owned('categories', $request)->where('id', $data['category_id'])->lockForUpdate()->first();
            $this->category($request, $data['category_id'], $data['type']);
            $data['transaction_date'] ??= $old?->transaction_date ?? now()->format('Y-m-d H:i:s');
            if ($id) {
                $this->owned('transactions', $request)->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                $id = DB::table('transactions')->insertGetId($data + ['user_id' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
            }

            return $this->ok([
                'transaction' => $this->record('transactions', $request, $id),
                'balance' => $this->totals($request),
                'budget_alerts' => (new BudgetController)->report($request),
            ], $old ? 200 : 201);
        });
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
