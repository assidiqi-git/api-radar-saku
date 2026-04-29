<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransactionTypeRequest;
use App\Http\Requests\UpdateTransactionTypeRequest;
use App\Http\Resources\TransactionTypeResource;
use App\Models\TransactionType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionTypeController extends Controller
{
    /**
     * List transaction types.
     *
     * Returns a paginated list of all transaction types belonging to the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $types = TransactionType::latest()->paginate(15);

        return TransactionTypeResource::collection($types);
    }

    /**
     * Create a transaction type.
     *
     * Creates a new transaction type (e.g. income, outcome, saving) for the authenticated user.
     *
     * @response 201 TransactionTypeResource
     */
    public function store(StoreTransactionTypeRequest $request): TransactionTypeResource
    {
        $type = TransactionType::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return new TransactionTypeResource($type);
    }

    /**
     * Get a transaction type.
     *
     * Returns a specific transaction type. Returns 404 if it does not belong to the authenticated user.
     */
    public function show(TransactionType $transactionType): TransactionTypeResource
    {
        return new TransactionTypeResource($transactionType);
    }

    /**
     * Update a transaction type.
     *
     * Renames or updates the description of a transaction type. All fields are optional.
     */
    public function update(UpdateTransactionTypeRequest $request, TransactionType $transactionType): TransactionTypeResource
    {
        $transactionType->update($request->validated());

        return new TransactionTypeResource($transactionType);
    }

    /**
     * Delete a transaction type.
     *
     * Permanently deletes a transaction type and all its associated categories (cascade).
     *
     * @response 204
     */
    public function destroy(TransactionType $transactionType): JsonResponse
    {
        $transactionType->delete();

        return response()->json(null, 204);
    }
}
