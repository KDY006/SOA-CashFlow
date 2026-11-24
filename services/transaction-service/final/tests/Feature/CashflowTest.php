<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CashflowTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $type = 'EXPENSE', int $user = 1): int
    {
        return $this->withHeader('X-User-Id', (string) $user)->postJson('/api/categories', ['name' => $type, 'type' => $type])->assertCreated()->json('data.id');
    }

    public function test_transaction_crud_recalculates_balance_and_rejects_invalid_update(): void
    {
        $income = $this->category('INCOME');
        $expense = $this->category();
        $this->postJson('/api/transactions', ['category_id' => $income, 'type' => 'INCOME', 'amount' => '1000.10'])->assertCreated()->assertJsonPath('data.balance.balance', '1000.10');
        $id = $this->postJson('/api/transactions', ['category_id' => $expense, 'type' => 'EXPENSE', 'amount' => '200.05'])->assertCreated()->assertJsonPath('data.balance.balance', '800.05')->json('data.transaction.id');
        $this->getJson('/api/transactions/'.$id)->assertOk();
        $this->putJson('/api/transactions/'.$id, ['category_id' => $expense, 'type' => 'EXPENSE', 'amount' => '-1'])->assertUnprocessable();
        $this->putJson('/api/transactions/'.$id, ['category_id' => $expense, 'type' => 'EXPENSE', 'amount' => '300.05'])->assertOk()->assertJsonPath('data.balance.balance', '700.05');
        $this->putJson('/api/transactions/'.$id, ['category_id' => $income, 'type' => 'INCOME', 'amount' => '300.05'])->assertOk()->assertJsonPath('data.balance.balance', '1300.15');
        $this->deleteJson('/api/transactions/'.$id)->assertOk()->assertJsonPath('data.balance.balance', '1000.10');
        $this->getJson('/api/balance')->assertOk()->assertJsonPath('data.balance', '1000.10');
    }

    public function test_quarter_budget_boundaries_progress_and_duplicate_validation(): void
    {
        $category = $this->category();
        $body = ['category_id' => $category, 'period' => 'QUARTERLY', 'start_date' => '2026-05-15', 'amount_limit' => '1000'];
        $id = $this->postJson('/api/budgets', $body)->assertCreated()->assertJsonPath('data.start_date', '2026-04-01')->assertJsonPath('data.end_date', '2026-06-30')->json('data.id');
        $this->postJson('/api/budgets', $body)->assertUnprocessable();
        $transaction = ['category_id' => $category, 'type' => 'EXPENSE', 'amount' => '800', 'transaction_date' => '2026-06-30 23:59:59'];
        $tid = $this->postJson('/api/transactions', $transaction)->assertCreated()->assertJsonPath('data.budget_alerts.0.status', 'WARNING')->json('data.transaction.id');
        $transaction['amount'] = '1200';
        $this->putJson('/api/transactions/'.$tid, $transaction)->assertOk()->assertJsonPath('data.budget_alerts.0.status', 'EXCEEDED')->assertJsonPath('data.budget_alerts.0.over_amount', '200.00');
        $this->deleteJson('/api/transactions/'.$tid)->assertOk()->assertJsonPath('data.budget_alerts.0.status', 'SAFE');
        $transaction['transaction_date'] = '2026-07-01 00:00:00';
        $this->postJson('/api/transactions', $transaction)->assertCreated();
        $this->getJson('/api/budgets/progress')->assertOk()->assertJsonPath('data.0.spent', '0.00');
        $body['period'] = 'MONTHLY';
        $body['start_date'] = '2028-02-15';
        $this->putJson('/api/budgets/'.$id, $body)->assertOk()->assertJsonPath('data.end_date', '2028-02-29');
        $this->deleteJson('/api/budgets/'.$id)->assertOk();
    }

    public function test_user_isolation_applies_to_reads_and_writes(): void
    {
        $category = $this->category();
        $tid = $this->postJson('/api/transactions', ['category_id' => $category, 'type' => 'EXPENSE', 'amount' => 10])->json('data.transaction.id');
        $bid = $this->postJson('/api/budgets', ['category_id' => $category, 'amount_limit' => 100, 'period' => 'MONTHLY', 'start_date' => '2026-11-01'])->json('data.id');
        $this->withHeader('X-User-Id', '2');
        foreach (['categories' => $category, 'transactions' => $tid, 'budgets' => $bid] as $resource => $id) {
            $this->getJson('/api/'.$resource.'/'.$id)->assertNotFound();
            $this->putJson('/api/'.$resource.'/'.$id, [])->assertNotFound();
            $this->deleteJson('/api/'.$resource.'/'.$id)->assertNotFound();
            $this->getJson('/api/'.$resource.'?user_id=1')->assertJsonPath('data', []);
        }
        $this->postJson('/api/transactions', ['category_id' => $category, 'type' => 'EXPENSE', 'amount' => 10])->assertUnprocessable();
        $this->getJson('/api/balance?user_id=1')->assertJsonPath('data.balance', '0.00');
    }

    public function test_category_crud_and_references_are_protected(): void
    {
        $category = $this->category();
        $this->putJson('/api/categories/'.$category, ['name' => 'Food', 'type' => 'EXPENSE'])->assertOk();
        $this->postJson('/api/transactions', ['category_id' => $category, 'type' => 'INCOME', 'amount' => 10])->assertUnprocessable();
        $this->postJson('/api/transactions', ['category_id' => $category, 'type' => 'EXPENSE', 'amount' => '0.001'])->assertUnprocessable();
        $this->postJson('/api/transactions', ['category_id' => $category, 'type' => 'EXPENSE', 'amount' => 10])->assertCreated();
        $this->putJson('/api/categories/'.$category, ['name' => 'Food', 'type' => 'INCOME'])->assertConflict();
        $this->deleteJson('/api/categories/'.$category)->assertConflict();
        $other = $this->category('INCOME');
        $this->postJson('/api/budgets', ['category_id' => $other, 'amount_limit' => 100, 'period' => 'MONTHLY', 'start_date' => '2026-11-01'])->assertUnprocessable();
        $this->deleteJson('/api/categories/'.$other)->assertOk();
    }

    public function test_identity_is_required_and_filters_are_validated(): void
    {
        $this->getJson('/api/transactions')->assertUnauthorized();
        $this->withHeader('X-User-Id', '1')->getJson('/api/transactions?limit=-1')->assertUnprocessable();
        $this->getJson('/api/transactions?to_date=2026-11-30')->assertOk();
        $this->getJson('/api/transactions?from_date=2026-12-01&to_date=2026-11-30')->assertUnprocessable();
        $this->postJson('/api/budgets', ['category_id' => 1, 'amount_limit' => -10, 'period' => 'YEARLY', 'start_date' => '2026-02-30'])->assertUnprocessable();
        $this->getJson('/api/health')->assertOk();
    }
}
