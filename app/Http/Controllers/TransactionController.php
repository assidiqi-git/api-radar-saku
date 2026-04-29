<?php

namespace App\Http\Controllers;

use App\Enums\TransactionAction;
use App\Http\Requests\StoreTransactionRequest;
use App\Http\Resources\TransactionResource;
use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\Wallet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class TransactionController extends Controller
{
    /**
     * List transactions.
     *
     * Returns a paginated list of all transactions belonging to the authenticated user,
     * with wallet and category (including transaction type) included.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = Transaction::with(['wallet', 'transactionCategory.transactionType'])
            ->latest()
            ->paginate(15);

        return TransactionResource::collection($transactions);
    }

    /**
     * Record a transaction.
     *
     * Creates a new financial transaction and atomically updates the associated wallet balance.
     *
     * **Balance logic:**
     * - If the transaction type name is `income` → wallet balance is **increased** by `amount`.
     * - Any other type name (e.g. `outcome`, `saving`) → wallet balance is **decreased** by `amount`.
     *
     * The optional `photo` field accepts an image file (max 2MB) stored under `storage/transactions/`.
     * The response includes a `photo_url` pointing to the public URL of the uploaded image.
     *
     * Both `Transaction::create()` and `wallet->update()` are wrapped in a `DB::transaction()`
     * to guarantee atomicity. If either step fails, both are rolled back.
     *
     * @response 201 TransactionResource
     */
    public function store(StoreTransactionRequest $request): TransactionResource
    {
        $photoPath = null;

        if ($request->hasFile('photo')) {
            $photoPath = $request->file('photo')->store('transactions', 'public');
        }

        $transaction = DB::transaction(function () use ($request, $photoPath) {
            /** @var Wallet $wallet */
            $wallet = Wallet::withoutGlobalScopes()
                ->where('id', $request->validated('wallet_id'))
                ->where('user_id', $request->user()->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var TransactionCategory $category */
            $category = TransactionCategory::with('transactionType')
                ->withoutGlobalScopes()
                ->where('id', $request->validated('transaction_category_id'))
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            $action = $category->transactionType?->action;
            $amount = $request->validated('amount');

            $newBalance = match ($action) {
                TransactionAction::Addition => $wallet->balance + $amount,
                TransactionAction::Deduction => $wallet->balance - $amount,
                default => $wallet->balance, // neutral: no change
            };

            $transaction = Transaction::create([
                'user_id' => $request->user()->id,
                'wallet_id' => $wallet->id,
                'transaction_category_id' => $category->id,
                'amount' => $amount,
                'name' => $request->validated('name'),
                'note' => $request->validated('note'),
                'photo_path' => $photoPath,
            ]);

            $wallet->update(['balance' => $newBalance]);

            return $transaction;
        });

        $transaction->load(['wallet', 'transactionCategory.transactionType']);

        return new TransactionResource($transaction);
    }

    /**
     * Get a transaction.
     *
     * Returns a specific transaction with wallet and category details.
     * Returns 404 if it does not belong to the authenticated user.
     */
    public function show(Transaction $transaction): TransactionResource
    {
        $transaction->load(['wallet', 'transactionCategory.transactionType']);

        return new TransactionResource($transaction);
    }

    /**
     * Delete a transaction.
     *
     * Permanently deletes a transaction record. The associated photo file (if any) is also deleted
     * from storage. Note: wallet balance is NOT automatically reverted on deletion.
     *
     * @response 204
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        if ($transaction->photo_path) {
            Storage::disk('public')->delete($transaction->photo_path);
        }

        $transaction->delete();

        return response()->json(null, 204);
    }
}
