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
    public function index(Request $request): AnonymousResourceCollection
    {
        $wallets = Wallet::latest()->paginate(15);

        return WalletResource::collection($wallets);
    }

    public function store(StoreWalletRequest $request): WalletResource
    {
        $wallet = Wallet::create([
            ...$request->validated(),
            'user_id' => $request->user()->id,
            'balance' => $request->validated('balance', 0),
        ]);

        return new WalletResource($wallet);
    }

    public function show(Wallet $wallet): WalletResource
    {
        return new WalletResource($wallet);
    }

    public function update(UpdateWalletRequest $request, Wallet $wallet): WalletResource
    {
        $wallet->update($request->validated());

        return new WalletResource($wallet);
    }

    public function destroy(Wallet $wallet): JsonResponse
    {
        $wallet->delete();

        return response()->json(null, 204);
    }
}
