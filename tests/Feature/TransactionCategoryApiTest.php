<?php

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/transaction-categories')->assertStatus(401);
});

it('lists transaction categories belonging to the authenticated user', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();

    TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'Gaji',
        'description' => null,
    ]);

    $this->getJson('/api/transaction-categories')
        ->assertOk()
        ->assertJsonFragment(['name' => 'Gaji']);
});

it('does not show another user\'s categories', function () {
    $user = Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();
    TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $other->id,
        'transaction_type_id' => $otherType->id,
        'name' => 'Secret Category',
        'description' => null,
    ]);

    $this->getJson('/api/transaction-categories')
        ->assertOk()
        ->assertJsonMissing(['name' => 'Secret Category']);
});

it('creates a transaction category with valid data', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();

    $this->postJson('/api/transaction-categories', [
        'transaction_type_id' => $type->id,
        'name' => 'Freelance',
        'description' => 'Freelance income',
    ])
        ->assertStatus(201)
        ->assertJsonFragment(['name' => 'Freelance'])
        ->assertJsonPath('data.transaction_type.id', $type->id);

    $this->assertDatabaseHas('transaction_categories', ['name' => 'Freelance']);
});

it('fails to create a category without required fields', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/transaction-categories', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['transaction_type_id', 'name']);
});

it('fails to create a category with a transaction_type_id belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();

    $this->postJson('/api/transaction-categories', [
        'transaction_type_id' => $otherType->id,
        'name' => 'Cross-User',
    ])->assertStatus(422)->assertJsonValidationErrors(['transaction_type_id']);
});

it('shows a transaction category with nested transaction type', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();
    $category = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'Bonus',
        'description' => null,
    ]);

    $this->getJson("/api/transaction-categories/{$category->id}")
        ->assertOk()
        ->assertJsonFragment(['name' => 'Bonus'])
        ->assertJsonPath('data.transaction_type.id', $type->id);
});

it('returns 404 when showing a category belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();
    $otherCategory = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $other->id,
        'transaction_type_id' => $otherType->id,
        'name' => 'Other Cat',
        'description' => null,
    ]);

    $this->getJson("/api/transaction-categories/{$otherCategory->id}")->assertNotFound();
});

it('updates a transaction category name', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();
    $category = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'Old Name',
        'description' => null,
    ]);

    $this->putJson("/api/transaction-categories/{$category->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'New Name']);

    $this->assertDatabaseHas('transaction_categories', ['id' => $category->id, 'name' => 'New Name']);
});

it('fails to update a category with a cross-user transaction_type_id', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();
    $category = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'My Cat',
        'description' => null,
    ]);

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();

    $this->putJson("/api/transaction-categories/{$category->id}", [
        'transaction_type_id' => $otherType->id,
    ])->assertStatus(422)->assertJsonValidationErrors(['transaction_type_id']);
});

it('deletes a transaction category', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();
    $category = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'To Delete',
        'description' => null,
    ]);

    $this->deleteJson("/api/transaction-categories/{$category->id}")->assertStatus(204);

    $this->assertDatabaseMissing('transaction_categories', ['id' => $category->id]);
});

it('returns 409 when deleting a category that has associated transactions', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();
    $category = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'With Transactions',
        'description' => null,
    ]);

    // Create a transaction linked to this category
    Transaction::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'wallet_id' => Wallet::withoutGlobalScopes()->where('user_id', $user->id)->first()->id,
        'transaction_category_id' => $category->id,
        'amount' => 50000,
        'name' => 'Linked Transaction',
        'note' => null,
        'photo_path' => null,
    ]);

    $this->deleteJson("/api/transaction-categories/{$category->id}")
        ->assertStatus(409)
        ->assertJsonFragment(['message' => 'Cannot delete because it has associated records.']);

    // Category still exists
    $this->assertDatabaseHas('transaction_categories', ['id' => $category->id]);
});

it('returns 404 when deleting a category belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();
    $otherCategory = TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $other->id,
        'transaction_type_id' => $otherType->id,
        'name' => 'Other Cat',
        'description' => null,
    ]);

    $this->deleteJson("/api/transaction-categories/{$otherCategory->id}")->assertNotFound();
});
