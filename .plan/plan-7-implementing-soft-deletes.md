# Role & Context

You are an expert Laravel 13 developer. We are continuing the development of our Finance API. All 103 Pest tests are currently passing.

# Task: Implement Soft Deletes for Wallet, Transaction, and Transfer models.

# Instructions:

1. Create a single new migration file named `add_soft_deletes_to_finance_tables`.
2. In this migration, add `$table->softDeletes();` to the `wallets`, `transactions`, and `transfers` tables. Ensure you include the `down()` method to drop them.
3. Update the `Wallet`, `Transaction`, and `Transfer` Models to use the `Illuminate\Database\Eloquent\SoftDeletes` trait.
4. Update the `destroy` methods in `WalletController`, `TransactionController`, and `TransferController`. They should now perform soft deletes.
5. CRITICAL: For `TransactionController` and `TransferController`, when a transaction/transfer is deleted, you MUST reverse the balance mutation on the associated Wallet inside a `DB::transaction()`.
    - If deleting an income transaction, subtract the balance.
    - If deleting a transfer, return the money to the from_wallet and deduct from the to_wallet.
6. Update the corresponding Pest API Tests to assert that the database records are soft deleted (using `assertSoftDeleted`) and that the wallet balances are correctly restored after deletion.
7. Run `./vendor/bin/pint` and `php artisan test` to ensure all tests pass.
