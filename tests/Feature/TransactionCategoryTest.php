<?php

use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the transaction_categories table via migration', function () {
    expect(Schema::hasTable('transaction_categories'))->toBeTrue();
});

it('creates a transaction category using the factory', function () {
    $category = TransactionCategory::factory()->create();

    expect($category)->toBeInstanceOf(TransactionCategory::class)
        ->and($category->id)->not->toBeNull()
        ->and($category->name)->not->toBeEmpty();
});

it('transaction category belongs to a user', function () {
    $user = User::factory()->create();
    $category = TransactionCategory::factory()->for($user)->create();

    expect($category->user)->toBeInstanceOf(User::class)
        ->and($category->user->id)->toBe($user->id);
});

it('transaction category belongs to a transaction type', function () {
    $type = TransactionType::factory()->create();
    $category = TransactionCategory::factory()->for($type)->create();

    expect($category->transactionType)->toBeInstanceOf(TransactionType::class)
        ->and($category->transactionType->id)->toBe($type->id);
});

it('transaction category is deleted when user is deleted (cascade)', function () {
    $user = User::factory()->create();
    TransactionCategory::factory()->for($user)->create();

    $user->delete();

    expect(TransactionCategory::where('user_id', $user->id)->count())->toBe(0);
});

it('transaction category is deleted when transaction type is deleted (cascade)', function () {
    $type = TransactionType::factory()->create();
    TransactionCategory::factory()->for($type)->count(2)->create();

    $type->delete();

    expect(TransactionCategory::where('transaction_type_id', $type->id)->count())->toBe(0);
});
