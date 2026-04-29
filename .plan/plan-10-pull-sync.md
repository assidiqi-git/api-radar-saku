# Role & Context

You are an expert Laravel 13 developer. We are continuing our Finance API. The API now has ULIDs, Soft Deletes, and a Push Sync endpoint (Tahap 4). Now we need the "Pull" mechanism for our offline-first mobile app. All tests are currently passing.

# Task: Create a Pull Sync endpoint to send delta updates to the mobile client.

# Instructions:

1. In `SyncController`, create a new method `pullTransactions(Request $request)`.
2. Route: `GET /api/sync/transactions/pull` protected by `auth:sanctum`.
3. The endpoint should accept an optional query parameter `last_synced_at` (timestamp/ISO 8601).
4. Query Logic:
    - Start with `$query = Transaction::where('user_id', auth()->id())`.
    - CRITICAL: You MUST chain `->withTrashed()` so the client receives soft-deleted records (tombstones) and knows to delete them locally.
    - If `last_synced_at` is provided, add `->where('updated_at', '>=', $request->last_synced_at)`.
    - Order the results by `updated_at` ascending.
5. Return the results using `TransactionResource::collection()`.
6. Write Pest feature tests for `pullTransactions` verifying:
    - Initial sync (no `last_synced_at`) returns all data including soft-deleted ones.
    - Delta sync (with `last_synced_at`) only returns records updated or deleted after the timestamp.
7. Add PHPDoc for `dedoc/scramble` detailing the `last_synced_at` parameter.
8. Run `./vendor/bin/pint` and `php artisan test`.
