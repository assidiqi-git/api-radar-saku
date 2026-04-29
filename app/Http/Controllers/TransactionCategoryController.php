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
    /**
     * List transaction categories.
     *
     * Returns a paginated list of all transaction categories belonging to the authenticated user,
     * with their associated transaction type included.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = TransactionCategory::with('transactionType')->latest()->paginate(15);

        return TransactionCategoryResource::collection($categories);
    }

    /**
     * Create a transaction category.
     *
     * Creates a new category linked to a transaction type owned by the authenticated user.
     * Providing a `transaction_type_id` that belongs to another user will return a 422 error.
     *
     * @response 201 TransactionCategoryResource
     */
    public function store(StoreTransactionCategoryRequest $request): TransactionCategoryResource
    {
        $category = TransactionCategory::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $category->load('transactionType');

        return new TransactionCategoryResource($category);
    }

    /**
     * Get a transaction category.
     *
     * Returns a specific category with its transaction type. Returns 404 if it does not belong
     * to the authenticated user.
     */
    public function show(TransactionCategory $transactionCategory): TransactionCategoryResource
    {
        $transactionCategory->load('transactionType');

        return new TransactionCategoryResource($transactionCategory);
    }

    /**
     * Update a transaction category.
     *
     * Renames or changes the type of an existing category. All fields are optional.
     * The `transaction_type_id` must belong to the authenticated user.
     */
    public function update(UpdateTransactionCategoryRequest $request, TransactionCategory $transactionCategory): TransactionCategoryResource
    {
        $transactionCategory->update($request->validated());
        $transactionCategory->load('transactionType');

        return new TransactionCategoryResource($transactionCategory);
    }

    /**
     * Delete a transaction category.
     *
     * Permanently deletes a transaction category.
     * Returns 409 Conflict if any transactions are still associated with this category.
     *
     * @response 204
     * @response 409 {"message": "Cannot delete because it has associated records."}
     */
    public function destroy(TransactionCategory $transactionCategory): JsonResponse
    {
        if ($transactionCategory->transactions()->exists()) {
            return response()->json([
                'message' => 'Cannot delete because it has associated records.',
            ], 409);
        }

        $transactionCategory->delete();

        return response()->json(null, 204);
    }
}
