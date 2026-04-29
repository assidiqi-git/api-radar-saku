<?php

use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the transfers table via migration', function () {
    expect(Schema::hasTable('transfers'))->toBeTrue();
});

it('creates a transfer using the factory', function () {
    $transfer = Transfer::factory()->create();

    expect($transfer)->toBeInstanceOf(Transfer::class)
        ->and($transfer->id)->not->toBeNull()
        ->and($transfer->amount)->not->toBeNull()
        ->and($transfer->transfer_date)->not->toBeNull();
});

it('transfer belongs to a user', function () {
    $user = User::factory()->create();
    $transfer = Transfer::factory()->for($user)->create();

    expect($transfer->user)->toBeInstanceOf(User::class)
        ->and($transfer->user->id)->toBe($user->id);
});

it('transfer has a fromWallet relation', function () {
    $user = User::factory()->create();
    $fromWallet = Wallet::factory()->for($user)->create();
    $transfer = Transfer::factory()->for($user)->create(['from_wallet_id' => $fromWallet->id]);

    $this->actingAs($user);

    expect($transfer->fromWallet)->toBeInstanceOf(Wallet::class)
        ->and($transfer->fromWallet->id)->toBe($fromWallet->id);
});

it('transfer has a toWallet relation', function () {
    $user = User::factory()->create();
    $toWallet = Wallet::factory()->for($user)->create();
    $transfer = Transfer::factory()->for($user)->create(['to_wallet_id' => $toWallet->id]);

    $this->actingAs($user);

    expect($transfer->toWallet)->toBeInstanceOf(Wallet::class)
        ->and($transfer->toWallet->id)->toBe($toWallet->id);
});

it('transfer amount and fee are cast to decimal', function () {
    $transfer = Transfer::factory()->create([
        'amount' => '500000.00',
        'fee' => '2500.50',
    ]);

    expect($transfer->amount)->toBe('500000.00')
        ->and($transfer->fee)->toBe('2500.50');
});

it('transfer is deleted when user is deleted (cascade)', function () {
    $user = User::factory()->create();
    Transfer::factory()->for($user)->count(2)->create();

    $user->delete();

    expect(Transfer::where('user_id', $user->id)->count())->toBe(0);
});
