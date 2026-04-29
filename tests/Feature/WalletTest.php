<?php

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the wallets table via migration', function () {
    expect(Schema::hasTable('wallets'))->toBeTrue();
});

it('creates a wallet using the factory', function () {
    $wallet = Wallet::factory()->create();

    expect($wallet)->toBeInstanceOf(Wallet::class)
        ->and($wallet->id)->not->toBeNull()
        ->and($wallet->name)->not->toBeEmpty()
        ->and($wallet->type)->not->toBeEmpty()
        ->and($wallet->balance)->not->toBeNull();
});

it('wallet belongs to a user', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();

    expect($wallet->user)->toBeInstanceOf(User::class)
        ->and($wallet->user->id)->toBe($user->id);
});

it('wallet is deleted when user is deleted (cascade)', function () {
    $user = User::factory()->create();
    Wallet::factory()->for($user)->create();

    $user->delete();

    expect(Wallet::where('user_id', $user->id)->count())->toBe(0);
});

it('wallet balance is cast to decimal', function () {
    $wallet = Wallet::factory()->create(['balance' => '100.50']);

    expect($wallet->balance)->toBe('100.50');
});
