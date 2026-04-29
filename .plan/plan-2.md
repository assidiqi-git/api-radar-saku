## Task: Implement Authentication and User Data Isolation for the finance API.

## Instructions:

- Install and configure Laravel Sanctum for API token authentication.

- Create AuthController with login and logout endpoints. Return standard JSON responses with the Sanctum token.

- CRITICAL: Create a Laravel Global Scope called UserScope and apply it to Wallet, TransactionType, TransactionCategory, Transaction, and Transfer models. This scope must automatically filter queries by auth()->id().

- Create an event listener: When a new User registers/first logs in, automatically seed default TransactionType (income, outcome, saving) and a default Wallet for that specific user.

- Write Pest feature tests for authentication and to verify that User A cannot see User B's wallets or categories.
