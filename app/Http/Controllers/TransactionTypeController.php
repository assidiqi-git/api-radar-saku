<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionTypeRequest;
use App\Http\Requests\UpdateTransactionTypeRequest;
use App\Http\Resources\TransactionTypeResource;
use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TransactionTypeController extends Controller
{
    /**
     * List transaction types.
     *
     * Returns a paginated list of all transaction types belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $types = TransactionType::latest()->paginate(15);

        return $this->paginatedResponse($types, TransactionTypeResource::class);
    }

    /**
     * Create a transaction type.
     *
     * Creates a new transaction type (e.g. income, outcome, saving) for the authenticated user.
     *
     */
    public function store(StoreTransactionTypeRequest $request): JsonResponse
    {
        $type = TransactionType::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return $this->successResponse(
            new TransactionTypeResource($type),
            'Transaction type created successfully.',
            201,
        );
    }

    /**
     * Get a transaction type.
     *
     * Returns a specific transaction type. Returns 404 if it does not belong to the authenticated user.
     */
    public function show(TransactionType $transactionType): JsonResponse
    {
        return $this->successResponse(new TransactionTypeResource($transactionType));
    }

    /**
     * Update a transaction type.
     *
     * Renames or updates the description of a transaction type. All fields are optional.
     */
    public function update(UpdateTransactionTypeRequest $request, TransactionType $transactionType): JsonResponse
    {
        $transactionType->update($request->validated());

        return $this->successResponse(
            new TransactionTypeResource($transactionType),
            'Transaction type updated successfully.',
        );
    }

    /**
     * Delete a transaction type.
     *
     * Permanently deletes a transaction type.
     * Returns 409 Conflict if any categories are still associated with this type.
     *
     */
    public function destroy(TransactionType $transactionType): JsonResponse
    {
        if ($transactionType->transactionCategories()->exists()) {
            return $this->errorResponse(
                'Cannot delete because it has associated records.',
                code: 409,
            );
        }

        $transactionType->delete();

        return response()->json(null, 204);
    }
}
