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
    public function index(Request $request): AnonymousResourceCollection
    {
        $types = TransactionType::latest()->paginate(15);

        return TransactionTypeResource::collection($types);
    }

    public function store(StoreTransactionTypeRequest $request): TransactionTypeResource
    {
        $type = TransactionType::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
        ]);

        return new TransactionTypeResource($type);
    }

    public function show(TransactionType $transactionType): TransactionTypeResource
    {
        return new TransactionTypeResource($transactionType);
    }

    public function update(UpdateTransactionTypeRequest $request, TransactionType $transactionType): TransactionTypeResource
    {
        $transactionType->update($request->validated());

        return new TransactionTypeResource($transactionType);
    }

    public function destroy(TransactionType $transactionType): JsonResponse
    {
        $transactionType->delete();

        return response()->json(null, 204);
    }
}
