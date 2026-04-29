# Task: Create API endpoints for Wallets, Transaction Types, and Transaction Categories.

## Instructions:

- Create apiResource routes protected by auth:sanctum.
- Create Controllers, FormRequests (for validation), and API Resources (for consistent JSON formatting).
- Wallet Features: Create, Update (including balance adjustment), Delete.
- Type & Category Features: Create, Rename, Delete. Ensure a category is linked to a valid type.
- Keep controllers completely clean. Use FormRequests for authorization and validation. (Authorization should automatically pass if Global Scopes handle the ownership, but ensure validation blocks cross-user foreign keys).

- Write Pest feature tests for all CRUD operations, asserting correct database states and JSON structures.
