<?php

use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\QueryException;
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
    $user = User::factory()->create();
    $type = TransactionType::factory()->for($user)->create();
    $category = TransactionCategory::factory()->for($user)->for($type)->create();

    $this->actingAs($user);

    expect($category->transactionType)->toBeInstanceOf(TransactionType::class)
        ->and($category->transactionType->id)->toBe($type->id);
});

it('transaction category is deleted when user is deleted (cascade)', function () {
    $user = User::factory()->create();
    TransactionCategory::factory()->for($user)->create();

    $user->delete();

    expect(TransactionCategory::where('user_id', $user->id)->count())->toBe(0);
});

it('cannot delete a transaction type that has associated categories (restrict on delete)', function () {
    $type = TransactionType::factory()->create();
    TransactionCategory::factory()->for($type)->count(2)->create();

    // FK is now RESTRICT — deleting a type with categories must throw a QueryException
    expect(fn () => $type->delete())
        ->toThrow(QueryException::class);

    // Categories must still exist
    expect(TransactionCategory::withoutGlobalScopes()->where('transaction_type_id', $type->id)->count())->toBe(2);
});
