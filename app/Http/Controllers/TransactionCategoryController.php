<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionCategoryRequest;
use App\Http\Requests\UpdateTransactionCategoryRequest;
use App\Http\Resources\TransactionCategoryResource;
use App\Models\TransactionCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionCategoryController extends Controller
{
    /**
     * List transaction categories.
     *
     * Returns a paginated list of all transaction categories belonging to the authenticated user,
     * with their associated transaction type included.
     */
    public function index(Request $request): JsonResponse
    {
        $categories = TransactionCategory::with('transactionType')->latest()->paginate(15);

        return $this->paginatedResponse($categories, TransactionCategoryResource::class);
    }

    /**
     * Create a transaction category.
     *
     * Creates a new category linked to a transaction type owned by the authenticated user.
     * Providing a `transaction_type_id` that belongs to another user will return a 422 error.
     *
     */
    public function store(StoreTransactionCategoryRequest $request): JsonResponse
    {
        $category = TransactionCategory::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        $category->load('transactionType');

        return $this->successResponse(
            new TransactionCategoryResource($category),
            'Transaction category created successfully.',
            201,
        );
    }

    /**
     * Get a transaction category.
     *
     * Returns a specific category with its transaction type. Returns 404 if it does not belong
     * to the authenticated user.
     */
    public function show(TransactionCategory $transactionCategory): JsonResponse
    {
        $transactionCategory->load('transactionType');

        return $this->successResponse(new TransactionCategoryResource($transactionCategory));
    }

    /**
     * Update a transaction category.
     *
     * Renames or changes the type of an existing category. All fields are optional.
     * The `transaction_type_id` must belong to the authenticated user.
     */
    public function update(UpdateTransactionCategoryRequest $request, TransactionCategory $transactionCategory): JsonResponse
    {
        $transactionCategory->update($request->validated());
        $transactionCategory->load('transactionType');

        return $this->successResponse(
            new TransactionCategoryResource($transactionCategory),
            'Transaction category updated successfully.',
        );
    }

    /**
     * Delete a transaction category.
     *
     * Permanently deletes a transaction category.
     * Returns 409 Conflict if any transactions are still associated with this category.
     *
     */
    public function destroy(TransactionCategory $transactionCategory): JsonResponse
    {
        if ($transactionCategory->transactions()->exists()) {
            return $this->errorResponse(
                'Cannot delete because it has associated records.',
                code: 409,
            );
        }

        $transactionCategory->delete();

        return response()->json(null, 204);
    }
}
