<?php

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the transactions table via migration', function () {
    expect(Schema::hasTable('transactions'))->toBeTrue();
});

it('creates a transaction using the factory', function () {
    $transaction = Transaction::factory()->create();

    expect($transaction)->toBeInstanceOf(Transaction::class)
        ->and($transaction->id)->not->toBeNull()
        ->and($transaction->name)->not->toBeEmpty()
        ->and($transaction->amount)->not->toBeNull();
});

it('transaction belongs to a user', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create();

    expect($transaction->user)->toBeInstanceOf(User::class)
        ->and($transaction->user->id)->toBe($user->id);
});

it('transaction belongs to a wallet', function () {
    $user = User::factory()->create();
    $wallet = Wallet::factory()->for($user)->create();
    $transaction = Transaction::factory()->for($user)->for($wallet)->create();

    $this->actingAs($user);

    expect($transaction->wallet)->toBeInstanceOf(Wallet::class)
        ->and($transaction->wallet->id)->toBe($wallet->id);
});

it('transaction belongs to a transaction category', function () {
    $user = User::factory()->create();
    $category = TransactionCategory::factory()->for($user)->create();
    $transaction = Transaction::factory()->for($user)->for($category)->create();

    $this->actingAs($user);

    expect($transaction->transactionCategory)->toBeInstanceOf(TransactionCategory::class)
        ->and($transaction->transactionCategory->id)->toBe($category->id);
});

it('transaction amount is cast to decimal', function () {
    $transaction = Transaction::factory()->create(['amount' => '250000.75']);

    expect($transaction->amount)->toBe('250000.75');
});

it('transaction is deleted when user is deleted (cascade)', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->count(3)->create();

    $user->delete();

    expect(Transaction::where('user_id', $user->id)->count())->toBe(0);
});
