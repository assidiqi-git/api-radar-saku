<?php

use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('registers a new user and returns a token', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure(['token', 'user']);
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

it('logs in with valid credentials and returns a token', function () {
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

it('logs out and revokes the token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/logout');

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'Successfully logged out.']);

    // Token should be revoked — no tokens remain in DB
    expect($user->fresh()->tokens()->count())->toBe(0);
});

it('rejects logout without a token', function () {
    $this->postJson('/api/logout')
        ->assertStatus(401);
});

it('sets an httponly auth cookie on register', function () {
    $response = $this->postJson('/api/register', [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertStatus(201);

    $cookie = $response->getCookie('auth_token', decrypt: false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getValue())->not->toBeEmpty();
});

it('sets an httponly auth cookie on login', function () {
    User::factory()->create([
        'email' => 'test@example.com',
        'password' => bcrypt('password'),
    ]);

    $response = $this->postJson('/api/login', [
        'email' => 'test@example.com',
        'password' => 'password',
    ]);

    $response->assertStatus(200);

    $cookie = $response->getCookie('auth_token', decrypt: false);

    expect($cookie)->not->toBeNull()
        ->and($cookie->isHttpOnly())->toBeTrue()
        ->and($cookie->getValue())->not->toBeEmpty();
});

it('clears the auth cookie on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withToken($token)
        ->postJson('/api/logout');

    $response->assertStatus(200);

    $cookie = $response->getCookie('auth_token', decrypt: false);

    // Cookie should be expired (max-age = 0 or expiry in the past)
    expect($cookie)->not->toBeNull()
        ->and($cookie->getExpiresTime())->toBeLessThanOrEqual(time());
});
