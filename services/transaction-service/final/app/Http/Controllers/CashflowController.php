<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

abstract class CashflowController extends Controller
{
    protected function userId(Request $request): int
    {
        return $request->attributes->get('user_id');
    }

    protected function owned(string $table, Request $request)
    {
        return DB::table($table)->where('user_id', $this->userId($request));
    }

    protected function record(string $table, Request $request, int $id): object
    {
        return $this->owned($table, $request)->find($id) ?? abort(404);
    }

    protected function ok($data = null, int $status = 200)
    {
        return response()->json(['success' => true, 'data' => $data], $status);
    }

    protected function category(Request $request, int $id, string $type): object
    {
        $category = $this->owned('categories', $request)->find($id);
        if (! $category || $category->type !== $type) {
            throw ValidationException::withMessages(['category_id' => 'Category must belong to you and match the transaction type.']);
        }

        return $category;
    }

    protected function moneyRule(): array
    {
        return ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:9999999999999.99'];
    }
}
