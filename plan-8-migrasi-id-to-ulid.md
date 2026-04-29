# Role & Context

You are an expert Laravel 13 developer. We need to refactor our database primary keys from auto-increment integers to ULIDs to support an offline-first mobile client.

# Task: Refactor all migrations, models, and factories to use ULID.

# Instructions:

1. Modify the existing migrations for `wallets`, `transaction_types`, `transaction_categories`, `transactions`, and `transfers`:
    - Replace `$table->id()` with `$table->ulid('id')->primary()`.
    - Replace all `foreignId('..._id')` with `foreignUlid('..._id')` and ensure cascading works correctly.
2. Update ALL Models (`User`, `Wallet`, `TransactionType`, `TransactionCategory`, `Transaction`, `Transfer`):
    - Add the `Illuminate\Database\Eloquent\Concerns\HasUlids` trait.
    - Remove any integer casts for IDs if they exist.
3. Review all Factory files. Ensure that any manual ID assignments or foreign key generations are compatible with ULID strings.
4. Review all FormRequests (e.g., `StoreTransactionRequest`, `UpdateWalletRequest`). Update validation rules from `integer` to `string` or specific ULID regex if necessary, while keeping the `Rule::exists` logic intact.
5. Review all API Resources. Ensure IDs are cast/returned as strings.
6. Run `php artisan migrate:fresh --seed` (ensure it runs without errors).
7. Run `php artisan test`. Fix any Pest tests that fail due to strict integer assertions or ID structure changes.
