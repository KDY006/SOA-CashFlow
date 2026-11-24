<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CategoryController extends CashflowController
{
    public function index(Request $request)
    {
        $data = $request->validate(['type' => ['sometimes', Rule::in(['INCOME', 'EXPENSE'])]]);

        return $this->ok($this->owned('categories', $request)->when(isset($data['type']), fn ($q) => $q->where('type', $data['type']))->orderBy('id')->get());
    }

    public function show(Request $request, int $category)
    {
        return $this->ok($this->record('categories', $request, $category));
    }

    private function save(Request $request, ?int $id = null)
    {
        $old = $id ? $this->record('categories', $request, $id) : null;
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('categories')->where('user_id', $this->userId($request))->where('type', $request->input('type'))->ignore($id)],
            'type' => ['required', Rule::in(['INCOME', 'EXPENSE'])],
            'icon' => ['nullable', 'string', 'max:100'],
        ]);

        return DB::transaction(function () use ($request, $id, $old, $data) {
            if ($id) {
                $locked = $this->owned('categories', $request)->where('id', $id)->lockForUpdate()->first();
                abort_unless($locked, 404);
                if ($locked->type !== $data['type']) {
                    abort_if(DB::table('transactions')->where('category_id', $id)->exists() || DB::table('budgets')->where('category_id', $id)->exists(), 409, 'Cannot change the type of a category in use.');
                }
                $this->owned('categories', $request)->where('id', $id)->update($data + ['updated_at' => now()]);
            } else {
                $id = DB::table('categories')->insertGetId($data + ['user_id' => $this->userId($request), 'created_at' => now(), 'updated_at' => now()]);
            }

            return $this->ok($this->record('categories', $request, $id), $old ? 200 : 201);
        });
    }

    public function store(Request $request)
    {
        return $this->save($request);
    }

    public function update(Request $request, int $category)
    {
        return $this->save($request, $category);
    }

    public function destroy(Request $request, int $category)
    {
        return DB::transaction(function () use ($request, $category) {
            $this->record('categories', $request, $category);
            $this->owned('categories', $request)->where('id', $category)->lockForUpdate()->first();
            abort_if(DB::table('transactions')->where('category_id', $category)->exists() || DB::table('budgets')->where('category_id', $category)->exists(), 409, 'Category is in use.');
            $this->owned('categories', $request)->where('id', $category)->delete();

            return $this->ok();
        });
    }
}
