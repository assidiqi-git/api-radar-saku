<?php

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ─── Helpers ─────────────────────────────────────────────────────────────────

/**
 * Create a user with an income/outcome/saving wallet, type, and category.
 *
 * @return array{user: User, wallet: Wallet, category: TransactionCategory, type: TransactionType}
 */
function makeSyncUser(string $typeName = 'income'): array
{
    $user = User::factory()->create();
    $type = TransactionType::withoutGlobalScopes()
        ->where('user_id', $user->id)
        ->where('name', $typeName)
        ->first();
    $category = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'Sync Category',
        'description' => null,
    ]);
    $wallet = Wallet::withoutGlobalScopes()->where('user_id', $user->id)->first();
    $wallet->update(['balance' => 1_000_000]);

    return compact('user', 'wallet', 'category', 'type');
}

/** Generate a fresh ULID string. */
function newUlid(): string
{
    return strtoupper(Str::ulid()->toString());
}

// ─── Auth ─────────────────────────────────────────────────────────────────────

it('requires authentication for sync endpoint', function () {
    $this->postJson('/api/sync/transactions')->assertStatus(401);
});

// ─── Batch Insert ─────────────────────────────────────────────────────────────

it('inserts new transactions and mutates wallet balance (addition/income)', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [
            [
                'id' => $ulid,
                'wallet_id' => $wallet->id,
                'transaction_category_id' => $category->id,
                'amount' => 200_000,
                'name' => 'Gaji',
                'note' => null,
                'created_at' => now()->toIso8601String(),
                'deleted_at' => null,
            ],
        ],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    expect((float) $wallet->fresh()->balance)->toBe($initialBalance + 200_000);
    $this->assertDatabaseHas('transactions', ['id' => $ulid, 'name' => 'Gaji']);
});

it('inserts new transactions and decreases wallet balance (deduction/outcome)', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('outcome');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [
            [
                'id' => $ulid,
                'wallet_id' => $wallet->id,
                'transaction_category_id' => $category->id,
                'amount' => 50_000,
                'name' => 'Makan Siang',
                'note' => 'Warteg',
                'created_at' => now()->toIso8601String(),
                'deleted_at' => null,
            ],
        ],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    expect((float) $wallet->fresh()->balance)->toBe($initialBalance - 50_000);
});

// ─── Idempotency ──────────────────────────────────────────────────────────────

it('is idempotent — sending the same batch twice does not double-charge the wallet', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    $payload = [
        'transactions' => [
            [
                'id' => $ulid,
                'wallet_id' => $wallet->id,
                'transaction_category_id' => $category->id,
                'amount' => 100_000,
                'name' => 'Idempotent',
                'note' => null,
                'created_at' => now()->toIso8601String(),
                'deleted_at' => null,
            ],
        ],
    ];

    // First sync
    $this->postJson('/api/sync/transactions', $payload)
        ->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    // Second sync — must be skipped
    $this->postJson('/api/sync/transactions', $payload)
        ->assertOk()->assertJson(['synced' => 0, 'skipped' => 1]);

    // Balance must only change once
    expect((float) $wallet->fresh()->balance)->toBe($initialBalance + 100_000);
});

// ─── Delta Amount ─────────────────────────────────────────────────────────────

it('applies only the balance delta when an existing transaction amount is updated', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('outcome');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    // First sync: amount = 100_000
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 100_000,
            'name' => 'Delta Test',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    // Balance after first sync
    expect((float) $wallet->fresh()->balance)->toBe($initialBalance - 100_000);

    // Second sync: amount changed to 150_000
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 150_000,
            'name' => 'Delta Test',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    // Balance should only reflect the new total deduction, not cumulative
    expect((float) $wallet->fresh()->balance)->toBe($initialBalance - 150_000);
    $this->assertDatabaseHas('transactions', ['id' => $ulid, 'amount' => '150000.00']);
});

// ─── Soft Delete from Client ──────────────────────────────────────────────────

it('soft deletes an existing transaction and reverses wallet balance when deleted_at is provided', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    // First: insert
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 300_000,
            'name' => 'Will Be Deleted',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    expect((float) $wallet->fresh()->balance)->toBe($initialBalance + 300_000);

    // Second: client sends deleted_at
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 300_000,
            'name' => 'Will Be Deleted',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
        ]],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    // Balance must be reversed
    expect((float) $wallet->fresh()->balance)->toBe($initialBalance);
    $this->assertSoftDeleted('transactions', ['id' => $ulid]);
});

it('is idempotent when soft-deleting an already-deleted transaction', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    // Insert then delete
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 50_000,
            'name' => 'Temp',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 50_000,
            'name' => 'Temp',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
        ]],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    // Send deleted_at again — must be skipped
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 50_000,
            'name' => 'Temp',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
        ]],
    ])->assertOk()->assertJson(['synced' => 0, 'skipped' => 1]);

    // Balance stays at initial (reversed only once)
    expect((float) $wallet->fresh()->balance)->toBe($initialBalance);
});

it('creates transaction as new and immediately soft-deletes when deleted_at provided for unknown id', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('outcome');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $ulid = newUlid();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 75_000,
            'name' => 'Offline Deleted',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
        ]],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 0]);

    // Balance should be unaffected (deducted then re-added)
    expect((float) $wallet->fresh()->balance)->toBe($initialBalance);
    $this->assertSoftDeleted('transactions', ['id' => $ulid]);
});

// ─── Validation ───────────────────────────────────────────────────────────────

it('fails validation when transactions field is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/sync/transactions', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['transactions']);
});

it('fails validation when required transaction fields are missing', function () {
    ['user' => $user] = makeSyncUser();
    Sanctum::actingAs($user);

    $this->postJson('/api/sync/transactions', [
        'transactions' => [
            ['id' => newUlid()], // missing all other fields
        ],
    ])->assertStatus(422)
        ->assertJsonValidationErrors([
            'transactions.0.wallet_id',
            'transactions.0.transaction_category_id',
            'transactions.0.amount',
            'transactions.0.name',
        ]);
});

it('fails validation when wallet_id belongs to another user', function () {
    ['user' => $user, 'category' => $category] = makeSyncUser();
    Sanctum::actingAs($user);

    $otherUser = User::factory()->create();
    $otherWallet = Wallet::withoutGlobalScopes()->where('user_id', $otherUser->id)->first();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => newUlid(),
            'wallet_id' => $otherWallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 10_000,
            'name' => 'Hacked',
            'deleted_at' => null,
        ]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['transactions.0.wallet_id']);
});

it('fails validation when transaction_category_id belongs to another user', function () {
    ['user' => $user, 'wallet' => $wallet] = makeSyncUser();
    Sanctum::actingAs($user);

    $otherUser = User::factory()->create();
    $otherCategory = TransactionCategory::withoutGlobalScopes()
        ->where('user_id', $otherUser->id)
        ->first();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => newUlid(),
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $otherCategory?->id ?? newUlid(),
            'amount' => 10_000,
            'name' => 'Cross User Category',
            'deleted_at' => null,
        ]],
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['transactions.0.transaction_category_id']);
});

// ─── Mixed Batch ──────────────────────────────────────────────────────────────

it('handles a mixed batch of new and already-synced transactions correctly', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $initialBalance = (float) $wallet->balance;
    $existingUlid = newUlid();
    $newUlid = newUlid();

    // Pre-insert one transaction
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $existingUlid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 100_000,
            'name' => 'Already Synced',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    $balanceAfterFirst = (float) $wallet->fresh()->balance;

    // Send batch: one already synced (skip), one new (sync)
    $this->postJson('/api/sync/transactions', [
        'transactions' => [
            [
                'id' => $existingUlid,
                'wallet_id' => $wallet->id,
                'transaction_category_id' => $category->id,
                'amount' => 100_000,
                'name' => 'Already Synced',
                'note' => null,
                'created_at' => now()->toIso8601String(),
                'deleted_at' => null,
            ],
            [
                'id' => $newUlid,
                'wallet_id' => $wallet->id,
                'transaction_category_id' => $category->id,
                'amount' => 50_000,
                'name' => 'Brand New',
                'note' => null,
                'created_at' => now()->toIso8601String(),
                'deleted_at' => null,
            ],
        ],
    ])->assertOk()->assertJson(['synced' => 1, 'skipped' => 1]);

    // Only the new transaction's amount should be added
    expect((float) $wallet->fresh()->balance)->toBe($balanceAfterFirst + 50_000);
});

// ─── Pull Sync ────────────────────────────────────────────────────────────────

it('requires authentication for pull sync endpoint', function () {
    $this->getJson('/api/sync/transactions/pull')->assertStatus(401);
});

it('initial pull sync (no last_synced_at) returns all transactions including soft-deleted tombstones', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $activeUlid = newUlid();
    $deletedUlid = newUlid();

    // Push an active transaction
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $activeUlid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 100_000,
            'name' => 'Active',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    // Push and soft-delete another transaction
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $deletedUlid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 50_000,
            'name' => 'Deleted',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => now()->toIso8601String(),
        ]],
    ])->assertOk();

    $response = $this->getJson('/api/sync/transactions/pull')->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($activeUlid)
        ->toContain($deletedUlid);

    // Tombstone must have deleted_at populated
    $tombstone = collect($response->json('data'))->firstWhere('id', $deletedUlid);
    expect($tombstone['deleted_at'])->not->toBeNull();
});

it('delta pull sync returns only records updated at or after last_synced_at', function () {
    ['user' => $user, 'wallet' => $wallet, 'category' => $category] = makeSyncUser('income');
    Sanctum::actingAs($user);

    $oldUlid = newUlid();
    $newUlid = newUlid();

    // Freeze time at T+0, push "old" transaction
    $this->travelTo(now()->startOfSecond());

    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $oldUlid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 100_000,
            'name' => 'Old Transaction',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    // Advance to T+5s, set cursor as UTC datetime string
    $this->travel(5)->seconds();
    $syncCursor = now()->utc()->toDateTimeString();

    // Advance to T+10s and push "new" transaction
    $this->travel(5)->seconds();

    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $newUlid,
            'wallet_id' => $wallet->id,
            'transaction_category_id' => $category->id,
            'amount' => 200_000,
            'name' => 'New Transaction',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    // Delta pull: should only return the new transaction
    $response = $this->getJson("/api/sync/transactions/pull?last_synced_at={$syncCursor}")->assertOk();

    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($newUlid)
        ->not->toContain($oldUlid);
});

it('pull sync only returns transactions belonging to the authenticated user', function () {
    ['user' => $userA, 'wallet' => $walletA, 'category' => $categoryA] = makeSyncUser('income');
    ['user' => $userB, 'wallet' => $walletB, 'category' => $categoryB] = makeSyncUser('income');

    // UserA pushes a transaction
    Sanctum::actingAs($userA);
    $ulidA = newUlid();
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulidA,
            'wallet_id' => $walletA->id,
            'transaction_category_id' => $categoryA->id,
            'amount' => 100_000,
            'name' => 'UserA Tx',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    // UserB pushes a transaction
    Sanctum::actingAs($userB);
    $ulidB = newUlid();
    $this->postJson('/api/sync/transactions', [
        'transactions' => [[
            'id' => $ulidB,
            'wallet_id' => $walletB->id,
            'transaction_category_id' => $categoryB->id,
            'amount' => 200_000,
            'name' => 'UserB Tx',
            'note' => null,
            'created_at' => now()->toIso8601String(),
            'deleted_at' => null,
        ]],
    ])->assertOk();

    // UserA pulls — must only see their own transaction
    Sanctum::actingAs($userA);
    $response = $this->getJson('/api/sync/transactions/pull')->assertOk();
    $ids = collect($response->json('data'))->pluck('id');

    expect($ids)->toContain($ulidA)
        ->not->toContain($ulidB);
});
