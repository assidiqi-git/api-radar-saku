<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Helper: create a user with two wallets of specific balances.
 *
 * @return array{user: User, from: Wallet, to: Wallet}
 */
function makeUserWithWallets(float $fromBalance = 1000000, float $toBalance = 0): array
{
    $user = User::factory()->create();

    $from = Wallet::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'name' => 'From Wallet',
        'type' => 'cash',
        'balance' => $fromBalance,
    ]);

    $to = Wallet::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'name' => 'To Wallet',
        'type' => 'savings',
        'balance' => $toBalance,
    ]);

    return compact('user', 'from', 'to');
}

// ─── Auth ────────────────────────────────────────────────────────────────────

it('requires authentication', function () {
    $this->getJson('/api/transfers')->assertStatus(401);
    $this->postJson('/api/transfers')->assertStatus(401);
});

// ─── Index ───────────────────────────────────────────────────────────────────

it('lists transfers for the authenticated user', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000);
    Sanctum::actingAs($user);

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(201);

    $this->getJson('/api/transfers')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// ─── Store — balance logic ────────────────────────────────────────────────────

it('deducts amount from from_wallet and adds to to_wallet', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000, 500000);
    Sanctum::actingAs($user);

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 200000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(201);

    expect((float) $from->fresh()->balance)->toBe(800000.0);
    expect((float) $to->fresh()->balance)->toBe(700000.0);
});

it('deducts amount plus fee from from_wallet', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000, 0);
    Sanctum::actingAs($user);

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 300000,
        'fee' => 5000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(201);

    // from_wallet: 1000000 - 300000 - 5000 = 695000
    expect((float) $from->fresh()->balance)->toBe(695000.0);
    // to_wallet: only amount (fee goes to provider, not to_wallet)
    expect((float) $to->fresh()->balance)->toBe(300000.0);
});

// ─── Store — insufficient balance ────────────────────────────────────────────

it('returns 422 when from_wallet has insufficient balance', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(100000, 0);
    Sanctum::actingAs($user);

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 500000, // more than balance
        'transfer_date' => '2026-04-29',
    ])->assertStatus(422)
        ->assertJsonFragment(['message' => 'Insufficient balance.']);

    // Balances must remain unchanged
    expect((float) $from->fresh()->balance)->toBe(100000.0);
    expect((float) $to->fresh()->balance)->toBe(0.0);
});

it('returns 422 when balance is insufficient to cover amount plus fee', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(300000, 0);
    Sanctum::actingAs($user);

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 299000,
        'fee' => 5000, // 299000 + 5000 = 304000 > 300000
        'transfer_date' => '2026-04-29',
    ])->assertStatus(422);

    expect((float) $from->fresh()->balance)->toBe(300000.0);
});

// ─── Store — rollback ─────────────────────────────────────────────────────────

it('rolls back both wallet balances if an exception occurs inside DB::transaction', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000, 0);
    Sanctum::actingAs($user);

    $fromInitial = (float) $from->balance;
    $toInitial = (float) $to->balance;

    try {
        DB::transaction(function () use ($from, $to) {
            $from->decrement('balance', 500000);
            $to->increment('balance', 500000);
            throw new RuntimeException('Simulated failure mid-transfer');
        });
    } catch (RuntimeException) {
        // Expected
    }

    expect((float) $from->fresh()->balance)->toBe($fromInitial);
    expect((float) $to->fresh()->balance)->toBe($toInitial);
});

// ─── Store — validation ───────────────────────────────────────────────────────

it('fails when from_wallet and to_wallet are the same', function () {
    ['user' => $user, 'from' => $from] = makeUserWithWallets(1000000, 0);
    Sanctum::actingAs($user);

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $from->id,
        'amount' => 100000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['to_wallet_id']);
});

it('fails when using wallet belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    ['user' => $other, 'from' => $otherFrom, 'to' => $otherTo] = makeUserWithWallets();

    $this->postJson('/api/transfers', [
        'from_wallet_id' => $otherFrom->id,
        'to_wallet_id' => $otherTo->id,
        'amount' => 100000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(422)
        ->assertJsonValidationErrors(['from_wallet_id']);
});

it('fails when required fields are missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/transfers', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['from_wallet_id', 'to_wallet_id', 'amount', 'transfer_date']);
});

// ─── Show ─────────────────────────────────────────────────────────────────────

it('shows a specific transfer', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000, 0);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(201);

    $id = $response->json('data.id');

    $this->getJson("/api/transfers/{$id}")
        ->assertOk()
        ->assertJsonPath('data.id', $id)
        ->assertJsonPath('data.from_wallet.id', $from->id)
        ->assertJsonPath('data.to_wallet.id', $to->id);
});

it('returns 404 for a transfer belonging to another user', function () {
    ['user' => $owner, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000, 0);
    Sanctum::actingAs($owner);

    $response = $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(201);

    $id = $response->json('data.id');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson("/api/transfers/{$id}")->assertNotFound();
});

// ─── Destroy ─────────────────────────────────────────────────────────────────

it('deletes a transfer', function () {
    ['user' => $user, 'from' => $from, 'to' => $to] = makeUserWithWallets(1000000, 0);
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/transfers', [
        'from_wallet_id' => $from->id,
        'to_wallet_id' => $to->id,
        'amount' => 100000,
        'transfer_date' => '2026-04-29',
    ])->assertStatus(201);

    $id = $response->json('data.id');

    $this->deleteJson("/api/transfers/{$id}")->assertStatus(204);

    $this->assertDatabaseMissing('transfers', ['id' => $id]);
});
