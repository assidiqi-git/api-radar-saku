<?php

use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('user A cannot see wallets belonging to user B', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Explicitly add an extra wallet for User B beyond the observer's default
    Wallet::withoutGlobalScopes()->create([
        'user_id' => $userB->id,
        'name' => "User B's Extra Wallet",
        'type' => 'savings',
        'balance' => 500000,
    ]);

    // Acting as User A, only User A's own wallets should be visible (the one seeded by observer)
    $this->actingAs($userA);

    $wallets = Wallet::all();

    // All visible wallets must belong to User A
    expect($wallets->every(fn ($w) => $w->user_id === $userA->id))->toBeTrue();
    expect($wallets->contains(fn ($w) => $w->user_id === $userB->id))->toBeFalse();
});

it('user A cannot see transaction types belonging to user B', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);

    $types = TransactionType::all();

    // Only User A's transaction types are visible
    expect($types->every(fn ($t) => $t->user_id === $userA->id))->toBeTrue();
    expect($types->contains(fn ($t) => $t->user_id === $userB->id))->toBeFalse();
});

it('user A cannot see transaction categories belonging to user B', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Get one of User B's types (seeded by observer)
    $typeB = TransactionType::withoutGlobalScopes()
        ->where('user_id', $userB->id)
        ->first();

    TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $userB->id,
        'transaction_type_id' => $typeB->id,
        'name' => 'Groceries',
        'description' => null,
    ]);

    $this->actingAs($userA);

    $categories = TransactionCategory::all();

    expect($categories->every(fn ($c) => $c->user_id === $userA->id))->toBeTrue();
    expect($categories->contains(fn ($c) => $c->user_id === $userB->id))->toBeFalse();
});

it('user A can only see their own wallets', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    // Add one extra wallet each
    Wallet::withoutGlobalScopes()->create([
        'user_id' => $userA->id,
        'name' => "User A's Extra Wallet",
        'type' => 'cash',
        'balance' => 0,
    ]);

    Wallet::withoutGlobalScopes()->create([
        'user_id' => $userB->id,
        'name' => "User B's Extra Wallet",
        'type' => 'savings',
        'balance' => 0,
    ]);

    $this->actingAs($userA);

    $wallets = Wallet::all();

    expect($wallets->pluck('user_id')->unique()->all())->toBe([$userA->id]);
});

it('withoutGlobalScopes bypasses user isolation and sees all records', function () {
    $userA = User::factory()->create();
    $userB = User::factory()->create();

    $this->actingAs($userA);

    // With scope: only User A's wallets
    $scopedCount = Wallet::count();
    expect($scopedCount)->toBeGreaterThan(0);
    expect(Wallet::all()->every(fn ($w) => $w->user_id === $userA->id))->toBeTrue();

    // Without scope: both users' wallets are visible
    $allCount = Wallet::withoutGlobalScopes()->count();
    expect($allCount)->toBeGreaterThan($scopedCount);
    expect(Wallet::withoutGlobalScopes()->pluck('user_id')->unique()->count())->toBe(2);
});
