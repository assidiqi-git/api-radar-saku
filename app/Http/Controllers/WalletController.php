<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    /**
     * List wallets.
     *
     * Returns a paginated list of all wallets belonging to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $wallets = Wallet::latest()->paginate(15);

        return $this->paginatedResponse($wallets, WalletResource::class);
    }

    /**
     * Create a wallet.
     *
     * Creates a new wallet for the authenticated user.
     *
     * @response 201 WalletResource
     */
    public function store(StoreWalletRequest $request): JsonResponse
    {
        $wallet = Wallet::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'balance' => $request->validated('balance', 0),
        ]);

        return $this->successResponse(
            new WalletResource($wallet),
            'Wallet created successfully.',
            201,
        );
    }

    /**
     * Get a wallet.
     *
     * Returns details of a specific wallet. Returns 404 if it does not belong to the authenticated user.
     */
    public function show(Wallet $wallet): JsonResponse
    {
        return $this->successResponse(new WalletResource($wallet));
    }

    /**
     * Update a wallet.
     *
     * Partially updates wallet fields. All fields are optional (PATCH semantics).
     */
    public function update(UpdateWalletRequest $request, Wallet $wallet): JsonResponse
    {
        $wallet->update($request->validated());

        return $this->successResponse(
            new WalletResource($wallet),
            'Wallet updated successfully.',
        );
    }

    /**
     * Delete a wallet.
     *
     * Soft-deletes a wallet. The record is retained in the database but hidden from all queries.
     * Returns 404 if it does not belong to the authenticated user.
     *
     * @response 204
     */
    public function destroy(Wallet $wallet): JsonResponse
    {
        $wallet->delete();

        return response()->json(null, 204);
    }
}
