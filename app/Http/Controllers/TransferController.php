<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTransferRequest;
use App\Http\Resources\TransferResource;
use App\Models\Transfer;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class TransferController extends Controller
{
    /**
     * List transfers.
     *
     * Returns a paginated list of all fund transfers belonging to the authenticated user,
     * with source and destination wallet details included.
     */
    public function index(Request $request): JsonResponse
    {
        $transfers = Transfer::with(['fromWallet', 'toWallet'])
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse($transfers, TransferResource::class);
    }

    /**
     * Create a transfer.
     *
     * Atomically transfers funds between two wallets owned by the authenticated user.
     *
     * **Balance rules:**
     * - `from_wallet` is debited by `amount + fee`.
     * - `to_wallet` is credited by `amount` only (fee is not passed on).
     * - Both wallet updates and the transfer record creation are wrapped in a `DB::transaction()`.
     *
     * Returns 422 if `from_wallet` does not have sufficient balance to cover `amount + fee`.
     *
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $fromWallet = Wallet::withoutGlobalScopes()
            ->where('id', $request->validated('from_wallet_id'))
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $amount = $request->validated('amount');
        $fee = $request->validated('fee', 0) ?? 0;
        $totalDeduction = $amount + $fee;

        if ($fromWallet->balance < $totalDeduction) {
            return $this->errorResponse(
                'Insufficient balance.',
                ['from_wallet_id' => ['The wallet does not have sufficient balance for this transfer.']],
                422,
            );
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

        return $this->successResponse(
            new TransferResource($transfer),
            'Transfer created successfully.',
            201,
        );
    }

    /**
     * Get a transfer.
     *
     * Returns a specific transfer with source and destination wallet details.
     * Returns 404 if it does not belong to the authenticated user.
     */
    public function show(Transfer $transfer): JsonResponse
    {
        $transfer->load(['fromWallet', 'toWallet']);

        return $this->successResponse(new TransferResource($transfer));
    }

    /**
     * Delete a transfer.
     *
     * Soft-deletes a transfer and atomically reverses the wallet balance mutations:
     * - `from_wallet` is credited back by `amount + fee`
     * - `to_wallet` is debited by `amount`
     *
     * Both wallet updates and the soft delete are wrapped in a `DB::transaction()`.
     *
     */
    public function destroy(Transfer $transfer): JsonResponse
    {
        DB::transaction(function () use ($transfer) {
            /** @var Wallet $fromWallet */
            $fromWallet = Wallet::withoutGlobalScopes()
                ->where('id', $transfer->from_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Wallet $toWallet */
            $toWallet = Wallet::withoutGlobalScopes()
                ->where('id', $transfer->to_wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (float) $transfer->amount;
            $fee = (float) $transfer->fee;

            $fromWallet->increment('balance', $amount + $fee);
            $toWallet->decrement('balance', $amount);

            $transfer->delete();
        });

        return response()->json(null, 204);
    }
}
