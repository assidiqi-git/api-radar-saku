<?php

use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the transaction_types table via migration', function () {
    expect(Schema::hasTable('transaction_types'))->toBeTrue();
});

it('creates a transaction type using the factory', function () {
    $type = TransactionType::factory()->create();

    expect($type)->toBeInstanceOf(TransactionType::class)
        ->and($type->id)->not->toBeNull()
        ->and($type->name)->not->toBeEmpty();
});

it('transaction type belongs to a user', function () {
    $user = User::factory()->create();
    $type = TransactionType::factory()->for($user)->create();

    expect($type->user)->toBeInstanceOf(User::class)
        ->and($type->user->id)->toBe($user->id);
});

it('transaction type is deleted when user is deleted (cascade)', function () {
    $user = User::factory()->create();
    TransactionType::factory()->for($user)->create();

    $user->delete();

    expect(TransactionType::where('user_id', $user->id)->count())->toBe(0);
});
