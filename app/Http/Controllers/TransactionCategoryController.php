<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionCategoryRequest;
use App\Http\Requests\UpdateTransactionCategoryRequest;
use App\Http\Resources\TransactionCategoryResource;
use App\Models\TransactionCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = TransactionCategory::with('transactionType')->latest()->paginate(15);

        return TransactionCategoryResource::collection($categories);
    }

    public function store(StoreTransactionCategoryRequest $request): TransactionCategoryResource
    {
        $category = TransactionCategory::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $category->load('transactionType');

        return new TransactionCategoryResource($category);
    }

    public function show(TransactionCategory $transactionCategory): TransactionCategoryResource
    {
        $transactionCategory->load('transactionType');

        return new TransactionCategoryResource($transactionCategory);
    }

    public function update(UpdateTransactionCategoryRequest $request, TransactionCategory $transactionCategory): TransactionCategoryResource
    {
        $transactionCategory->update($request->validated());
        $transactionCategory->load('transactionType');

        return new TransactionCategoryResource($transactionCategory);
    }

    public function destroy(TransactionCategory $transactionCategory): JsonResponse
    {
        $transactionCategory->delete();

        return response()->json(null, 204);
    }
}
