<?php

use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\TransactionController;
use App\Http\Middleware\ResolveUser;
use Illuminate\Support\Facades\Route;

Route::get('/health', fn () => ['success' => true, 'data' => ['service' => 'transaction-budget', 'framework' => 'Laravel']]);
Route::middleware(ResolveUser::class)->group(function () {
    Route::apiResource('categories', CategoryController::class);
    Route::get('transactions/balance', [TransactionController::class, 'balance']);
    Route::get('balance', [TransactionController::class, 'balance']);
    Route::apiResource('transactions', TransactionController::class);
    Route::get('budgets/progress', [BudgetController::class, 'progress']);
    Route::apiResource('budgets', BudgetController::class);
});
