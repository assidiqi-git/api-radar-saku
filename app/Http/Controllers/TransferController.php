<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $transfers = Transfer::with(['fromWallet', 'toWallet'])
            ->latest()
            ->paginate(15);

        return TransferResource::collection($transfers);
    }

    public function store(StoreTransferRequest $request): TransferResource|JsonResponse
    {
        $fromWallet = Wallet::withoutGlobalScopes()
            ->where('id', $request->validated('from_wallet_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $amount = $request->validated('amount');
        $fee = $request->validated('fee', 0) ?? 0;
        $totalDeduction = $amount + $fee;

        if ($fromWallet->balance < $totalDeduction) {
            return response()->json([
                'message' => 'Insufficient balance.',
                'errors' => [
                    'from_wallet_id' => ['The wallet does not have sufficient balance for this transfer.'],
                ],
            ], 422);
        }

        $transfer = DB::transaction(function () use ($request, $amount, $fee, $totalDeduction) {
            /** @var Wallet $fromWallet */
            $fromWallet = Wallet::withoutGlobalScopes()
                ->where('id', $request->validated('from_wallet_id'))
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Wallet $toWallet */
            $toWallet = Wallet::withoutGlobalScopes()
                ->where('id', $request->validated('to_wallet_id'))
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromWallet->decrement('balance', $totalDeduction);
            $toWallet->increment('balance', $amount);

            return Transfer::create([
                'user_id' => $request->user()->id,
                'from_wallet_id' => $fromWallet->id,
                'to_wallet_id' => $toWallet->id,
                'amount' => $amount,
                'fee' => $fee,
                'transfer_date' => $request->validated('transfer_date'),
                'note' => $request->validated('note'),
            ]);
        });

        $transfer->load(['fromWallet', 'toWallet']);

        return new TransferResource($transfer);
    }

    public function show(Transfer $transfer): TransferResource
    {
        $transfer->load(['fromWallet', 'toWallet']);

        return new TransferResource($transfer);
    }

    public function destroy(Transfer $transfer): JsonResponse
    {
        $transfer->delete();

        return response()->json(null, 204);
    }
}
