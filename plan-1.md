# Role: You are an expert Laravel 13 Backend Developer.

# Task: Create the foundational Models, Migrations, and Factories for a personal finance tracking API.

## Entities & Requirements:

- **Wallet**: id, user_id, name, type, balance (decimal).
- **TransactionType**: id, user_id, name, description (e.g., income, outcome, saving). Note: Seed default types upon user creation later.
- **TransactionCategory**: id, user_id, transaction_type_id, name, description.
- **Transaction**: id, user_id, wallet_id, transaction_category_id, amount (decimal), name, note (nullable), photo_path (nullable).
- **Transfer**: id, user_id, from_wallet_id, to_wallet_id, amount (decimal),transfer_date, fee, note (nullable).

## Constraints:

- All tables MUST include a user_id foreign key referencing the users table with cascading deletes.
- Define strict relationships in all Models (e.g., a Wallet belongsTo a User, a Transaction belongsTo a Wallet).
- Enable mass assignment (guarded = []) or define fillable properly.

- Generate Pest PHP tests checking if migrations and factories work correctly. DO NOT use PHPUnit.
