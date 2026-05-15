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
    public function index(Request $request): JsonResponse
    {
        $transactions = Transaction::with(['wallet', 'transactionCategory.transactionType'])
            ->latest()
            ->paginate(15);

        return $this->paginatedResponse($transactions, TransactionResource::class);
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
     */
    public function store(StoreTransactionRequest $request): JsonResponse
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

        return $this->successResponse(
            new TransactionResource($transaction),
            'Transaction created successfully.',
            201,
        );
    }

    /**
     * Get a transaction.
     *
     * Returns a specific transaction with wallet and category details.
     * Returns 404 if it does not belong to the authenticated user.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        $transaction->load(['wallet', 'transactionCategory.transactionType']);

        return $this->successResponse(new TransactionResource($transaction));
    }

    /**
     * Delete a transaction.
     *
     * Soft-deletes a transaction and atomically reverses the associated wallet balance mutation.
     * If the transaction has a photo, the file is moved to `transactions/trash/` and the
     * `photo_path` column is updated to reflect the new location before soft deletion.
     *
     * **Balance reversal logic:**
     * - `addition` transaction → wallet balance is **decreased** by `amount`
     * - `deduction` transaction → wallet balance is **increased** by `amount`
     * - `neutral` transaction → no balance change
     *
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        DB::transaction(function () use ($transaction) {
            $transaction->load(['wallet', 'transactionCategory.transactionType']);

            /** @var Wallet $wallet */
            $wallet = Wallet::withoutGlobalScopes()
                ->where('id', $transaction->wallet_id)
                ->lockForUpdate()
                ->firstOrFail();

            $action = $transaction->transactionCategory?->transactionType?->action;
            $amount = (float) $transaction->amount;

            $newBalance = match ($action) {
                TransactionAction::Addition => $wallet->balance - $amount,
                TransactionAction::Deduction => $wallet->balance + $amount,
                default => $wallet->balance,
            };

            $wallet->update(['balance' => $newBalance]);

            if ($transaction->photo_path) {
                $filename = basename($transaction->photo_path);
                $trashedPath = 'transactions/trash/'.$filename;
                Storage::disk('public')->move($transaction->photo_path, $trashedPath);
                $transaction->photo_path = $trashedPath;
            }

            $transaction->save();
            $transaction->delete();
        });

        return response()->json(null, 204);
    }
}
