<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/wallets')->assertStatus(401);
});

it('lists wallets belonging to the authenticated user', function () {
    $user = Sanctum::actingAs(User::factory()->create());

    Wallet::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => 'My Wallet', 'type' => 'cash', 'balance' => 0]);
    // Another user's wallet — should NOT appear
    $other = User::factory()->create();
    Wallet::withoutGlobalScopes()->create(['user_id' => $other->id, 'name' => 'Other Wallet', 'type' => 'savings', 'balance' => 0]);

    $this->getJson('/api/wallets')
        ->assertOk()
        ->assertJsonFragment(['name' => 'My Wallet'])
        ->assertJsonMissing(['name' => 'Other Wallet']);
});

it('creates a wallet with valid data', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/wallets', [
        'name' => 'Tabungan',
        'type' => 'savings',
        'balance' => 500000,
    ])
        ->assertStatus(201)
        ->assertJsonFragment(['name' => 'Tabungan', 'type' => 'savings']);

    $this->assertDatabaseHas('wallets', ['name' => 'Tabungan']);
});

it('fails to create a wallet with invalid type', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/wallets', [
        'name' => 'Bad Wallet',
        'type' => 'invalid-type',
    ])->assertStatus(422)->assertJsonValidationErrors(['type']);
});

it('fails to create a wallet without required fields', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/wallets', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'type']);
});

it('shows a wallet belonging to the authenticated user', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $wallet = Wallet::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => 'My Wallet', 'type' => 'cash', 'balance' => 0]);

    $this->getJson("/api/wallets/{$wallet->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $wallet->id, 'name' => 'My Wallet']);
});

it('returns 404 when showing a wallet belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherWallet = Wallet::withoutGlobalScopes()->create(['user_id' => $other->id, 'name' => 'Other', 'type' => 'cash', 'balance' => 0]);

    $this->getJson("/api/wallets/{$otherWallet->id}")->assertNotFound();
});

it('updates a wallet', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $wallet = Wallet::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => 'Old Name', 'type' => 'cash', 'balance' => 0]);

    $this->putJson("/api/wallets/{$wallet->id}", ['name' => 'New Name', 'balance' => 100000])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);

    $this->assertDatabaseHas('wallets', ['id' => $wallet->id, 'name' => 'New Name', 'balance' => 100000]);
});

it('returns 404 when updating a wallet belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherWallet = Wallet::withoutGlobalScopes()->create(['user_id' => $other->id, 'name' => 'Other', 'type' => 'cash', 'balance' => 0]);

    $this->putJson("/api/wallets/{$otherWallet->id}", ['name' => 'Hacked'])->assertNotFound();
});

it('deletes a wallet', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $wallet = Wallet::withoutGlobalScopes()->create(['user_id' => $user->id, 'name' => 'To Delete', 'type' => 'cash', 'balance' => 0]);

    $this->deleteJson("/api/wallets/{$wallet->id}")->assertStatus(204);

    $this->assertSoftDeleted('wallets', ['id' => $wallet->id]);
});

it('returns 404 when deleting a wallet belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherWallet = Wallet::withoutGlobalScopes()->create(['user_id' => $other->id, 'name' => 'Other', 'type' => 'cash', 'balance' => 0]);

    $this->deleteJson("/api/wallets/{$otherWallet->id}")->assertNotFound();
});
