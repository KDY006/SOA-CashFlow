<?php

namespace App\Http\Controllers;

use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class BudgetController extends CashflowController
{
    public function index(Request $request)
    {
        return $this->ok($this->owned('budgets', $request)->orderByDesc('start_date')->get());
    }

    public function show(Request $request, int $budget)
    {
        return $this->ok($this->record('budgets', $request, $budget));
    }

    public function report(Request $request): array
    {
        return $this->owned('budgets', $request)->orderByDesc('start_date')->get()->map(function ($budget) use ($request) {
            $spent = $this->owned('transactions', $request)->where('type', 'EXPENSE')->where('category_id', $budget->category_id)
                ->whereBetween('transaction_date', [$budget->start_date.' 00:00:00', $budget->end_date.' 23:59:59'])->sum('amount');
            $spent = bcadd((string) $spent, '0', 2);
            $limit = (string) $budget->amount_limit;
            $remaining = bcsub($limit, $spent, 2);

            return (array) $budget + [
                'spent' => $spent,
                'remaining' => bccomp($remaining, '0', 2) < 0 ? '0.00' : $remaining,
                'over_amount' => bccomp($remaining, '0', 2) < 0 ? bcsub('0', $remaining, 2) : '0.00',
                'percentage' => round((float) bcdiv(bcmul($spent, '100', 4), $limit, 4), 2),
                'status' => bccomp($spent, $limit, 2) >= 0 ? 'EXCEEDED' : (bccomp(bcmul($spent, '100', 2), bcmul($limit, '80', 2), 2) >= 0 ? 'WARNING' : 'SAFE'),
            ];
        })->all();
    }

    public function progress(Request $request)
    {
        return $this->ok($this->report($request));
    }

    private function save(Request $request, ?int $id = null)
    {
        if ($id) {
            $this->record('budgets', $request, $id);
        }
        $data = $request->validate([
            'category_id' => ['required', 'integer', 'min:1'],
            'amount_limit' => $this->moneyRule(),
            'period' => ['required', Rule::in(['MONTHLY', 'QUARTERLY'])],
            'start_date' => ['required', 'date_format:Y-m-d'],
        ]);
        $date = CarbonImmutable::parse($data['start_date']);
        $start = $data['period'] === 'MONTHLY' ? $date->startOfMonth() : $date->startOfQuarter();
        $end = $data['period'] === 'MONTHLY' ? $date->endOfMonth() : $date->endOfQuarter();
        $data['start_date'] = $start->toDateString();
        $data['end_date'] = $end->toDateString();

        return DB::transaction(function () use ($request, $data, $id) {
            // Serialize budget changes per category, including concurrent duplicate creation.
            $this->owned('categories', $request)->where('id', $data['category_id'])->lockForUpdate()->first();
            $this->category($request, $data['category_id'], 'EXPENSE');
            $duplicate = $this->owned('budgets', $request)->where('category_id', $data['category_id'])->where('period', $data['period'])->where('start_date', $data['start_date']);
            if ($id) {
                $duplicate->where('id', '!=', $id);
            }
            if ($duplicate->exists()) {
                throw ValidationException::withMessages(['start_date' => 'A budget already exists for this category and period.']);
            }
            $created = ! $id;
            if ($id) {
                $this->owned('budgets', $request)->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                $id = DB::table('budgets')->insertGetId($data + ['user_id' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
            }

            return $this->ok($this->record('budgets', $request, $id), $created ? 201 : 200);
        });
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $budget)
    {
        return $this->save($request, $budget);
    }

    public function destroy(Request $request, int $budget)
    {
        $this->record('budgets', $request, $budget);
        $this->owned('budgets', $request)->where('id', $budget)->delete();

        return $this->ok();
    }
}
