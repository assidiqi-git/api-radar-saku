# Role & Context

You are an expert Laravel 13 developer. We have 103 passing tests. The user wants `TransactionType` to be dynamic. Currently, `TransactionController` calculates wallet balances by hardcoding the type name ('income' vs 'outcome'). We also need to change the cascading deletes for TransactionType and TransactionCategory to Restrict on Delete.

# Task: Refactor TransactionType for dynamic math actions and implement Restrict on Delete.

# Instructions:

1. Create a migration `refactor_transaction_types_and_foreign_keys`.
2. In the migration:
    - Add a string column `action` to `transaction_types` (allowed: 'addition', 'deduction', 'neutral'). Default: 'neutral'.
    - Drop the cascade foreign keys for `transaction_type_id` (on categories) and `transaction_category_id` (on transactions). Recreate them with `restrictOnDelete()`.
3. Update `UserObserver`: Set `action` explicitly for seeded types ('income' = 'addition', 'outcome' = 'deduction', 'saving' = 'deduction').
4. Update `StoreTransactionTypeRequest` & `UpdateTransactionTypeRequest` to validate the `action` field.
5. Refactor `TransactionController` & `TransferController`:
    - Calculate wallet balances based on `$transaction->category->transactionType->action` instead of type name.
6. Refactor `destroy` in `TransactionTypeController` & `TransactionCategoryController`:
    - If they have child records (categories or transactions respectively), abort and return `409 Conflict` JSON: "Cannot delete because it has associated records."
7. Update API Resources and Tests (add tests for 409 responses).
8. Run `./vendor/bin/pint` and `php artisan test`.
