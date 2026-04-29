<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWalletRequest;
use App\Http\Requests\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WalletController extends Controller
{
    /**
     * List wallets.
     *
     * Returns a paginated list of all wallets belonging to the authenticated user.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $wallets = Wallet::latest()->paginate(15);

        return WalletResource::collection($wallets);
    }

    /**
     * Create a wallet.
     *
     * Creates a new wallet for the authenticated user.
     *
     * @response 201 WalletResource
     */
    public function store(StoreWalletRequest $request): WalletResource
    {
        $wallet = Wallet::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'balance' => $request->validated('balance', 0),
        ]);

        return new WalletResource($wallet);
    }

    /**
     * Get a wallet.
     *
     * Returns details of a specific wallet. Returns 404 if it does not belong to the authenticated user.
     */
    public function show(Wallet $wallet): WalletResource
    {
        return new WalletResource($wallet);
    }

    /**
     * Update a wallet.
     *
     * Partially updates wallet fields. All fields are optional (PATCH semantics).
     */
    public function update(UpdateWalletRequest $request, Wallet $wallet): WalletResource
    {
        $wallet->update($request->validated());

        return new WalletResource($wallet);
    }

    /**
     * Delete a wallet.
     *
     * Permanently deletes a wallet. Returns 404 if it does not belong to the authenticated user.
     *
     * @response 204
     */
    public function destroy(Wallet $wallet): JsonResponse
    {
        $wallet->delete();

        return response()->json(null, 204);
    }
}
