<?php

namespace App\Http\Controllers;

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
    public function index(Request $request): AnonymousResourceCollection
    {
        $transactions = Transaction::with(['wallet', 'transactionCategory.transactionType'])
            ->latest()
            ->paginate(15);

        return TransactionResource::collection($transactions);
    }

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

            $typeName = $category->transactionType?->name;
            $amount = $request->validated('amount');

            $newBalance = $typeName === 'income'
                ? $wallet->balance + $amount
                : $wallet->balance - $amount;

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

    public function show(Transaction $transaction): TransactionResource
    {
        $transaction->load(['wallet', 'transactionCategory.transactionType']);

        return new TransactionResource($transaction);
    }

    public function destroy(Transaction $transaction): JsonResponse
    {
        if ($transaction->photo_path) {
            Storage::disk('public')->delete($transaction->photo_path);
        }

        $transaction->delete();

        return response()->json(null, 204);
    }
}
