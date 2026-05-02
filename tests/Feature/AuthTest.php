<?php

use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// -------------------------------------------------------------------------
// Register
// -------------------------------------------------------------------------

it('registers a new user (mobile) and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'user']);
});

it('registers a new user (web) and returns user data without a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ], ['X-Client-Type' => 'web']);

    $response->assertStatus(201)
        ->assertJsonStructure(['user'])
        ->assertJsonMissing(['token' => '']);
});

it('seeds 3 default transaction types when a user registers', function () {
    $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::first();

    expect(
        TransactionType::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->count()
    )->toBe(3);

    expect(
        TransactionType::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->pluck('name')
            ->sort()
            ->values()
            ->all()
    )->toBe(['income', 'outcome', 'saving']);
});

it('seeds a default wallet when a user registers', function () {
    $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::first();

    expect(
        Wallet::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->count()
    )->toBe(1);

    expect(
        Wallet::withoutGlobalScopes()
            ->where('user_id', $user->id)
            ->first()->name
    )->toBe('Dompet Utama');
});

it('fails registration with duplicate email', function () {
    User::factory()->create(['email' => 'test@example.com']);

    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

// -------------------------------------------------------------------------
// Login
// -------------------------------------------------------------------------

it('logs in with valid credentials (mobile) and returns a token', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200)
        ->assertJsonStructure(['token', 'user']);
});

it('logs in with valid credentials (web) and returns 204 without a token', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ], ['X-Client-Type' => 'web']);

    $response->assertStatus(204);
});

it('rejects login with wrong credentials', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'wrong-password',
    ]);

    $response->assertStatus(422)
        ->assertJsonFragment(['message' => 'The provided credentials are incorrect.']);
});

// -------------------------------------------------------------------------
// Logout
// -------------------------------------------------------------------------

it('logs out (mobile) and revokes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Successfully logged out.']);

    // Token should be revoked — no tokens remain in DB
    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('logs out (web) and invalidates the session', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/logout', [], ['X-Client-Type' => 'web']);

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Successfully logged out.']);
});

it('rejects logout without a token', function () {
    $this->postJson('/api/logout')
        ->assertStatus(401);
});

// -------------------------------------------------------------------------
// GET /user
// -------------------------------------------------------------------------

it('returns the authenticated user via Bearer token (mobile)', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/user')
        ->assertStatus(200)
        ->assertJsonFragment(['email' => $user->email]);
});

it('returns the authenticated user via session cookie (web)', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson('/api/user')
        ->assertStatus(200)
        ->assertJsonFragment(['email' => $user->email]);
});

it('rejects GET /user when unauthenticated', function () {
    $this->getJson('/api/user')
        ->assertStatus(401);
});
