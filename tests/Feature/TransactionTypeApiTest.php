<?php

use App\Enums\TransactionAction;
use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/transaction-types')->assertStatus(401);
});

it('lists transaction types belonging to the authenticated user', function () {
    $user = Sanctum::actingAs(User::factory()->create());

    // Observer already seeded 3 default types — verify they appear
    $this->getJson('/api/transaction-types')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('does not show another user\'s transaction types', function () {
    $user = Sanctum::actingAs(User::factory()->create());

    // User B has their own types (seeded by observer)
    User::factory()->create();

    // User A sees only their own 3 default types
    $this->getJson('/api/transaction-types')
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('creates a transaction type with valid data', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/transaction-types', [
        'name' => 'investment',
        'action' => TransactionAction::Addition->value,
        'description' => 'Investment type',
    ])
        ->assertStatus(201)
        ->assertJsonFragment(['name' => 'investment', 'action' => 'addition']);

    $this->assertDatabaseHas('transaction_types', ['name' => 'investment', 'action' => 'addition']);
});

it('fails to create a transaction type without a name', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/transaction-types', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['name', 'action']);
});

it('fails to create a transaction type with an invalid action', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/transaction-types', [
        'name' => 'test',
        'action' => 'invalid-action',
    ])->assertStatus(422)->assertJsonValidationErrors(['action']);
});

it('shows a transaction type belonging to the authenticated user', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();

    $this->getJson("/api/transaction-types/{$type->id}")
        ->assertOk()
        ->assertJsonFragment(['id' => $type->id]);
});

it('returns 404 when showing a transaction type belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();

    $this->getJson("/api/transaction-types/{$otherType->id}")->assertNotFound();
});

it('updates a transaction type name', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();

    $this->putJson("/api/transaction-types/{$type->id}", ['name' => 'renamed'])
        ->assertOk()
        ->assertJsonFragment(['name' => 'renamed']);

    $this->assertDatabaseHas('transaction_types', ['id' => $type->id, 'name' => 'renamed']);
});

it('returns 404 when updating a transaction type belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();

    $this->putJson("/api/transaction-types/{$otherType->id}", ['name' => 'hacked'])->assertNotFound();
});

it('deletes a transaction type', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    // Get income type (seeded by observer) — it has no categories yet
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();

    $this->deleteJson("/api/transaction-types/{$type->id}")->assertStatus(204);

    $this->assertDatabaseMissing('transaction_types', ['id' => $type->id]);
});

it('returns 409 when deleting a transaction type that has associated categories', function () {
    $user = Sanctum::actingAs(User::factory()->create());
    $type = TransactionType::withoutGlobalScopes()->where('user_id', $user->id)->first();

    // Create a category for this type
    TransactionCategory::withoutGlobalScopes()->create([
        'user_id' => $user->id,
        'transaction_type_id' => $type->id,
        'name' => 'Linked Category',
        'description' => null,
    ]);

    $this->deleteJson("/api/transaction-types/{$type->id}")
        ->assertStatus(409)
        ->assertJsonFragment(['message' => 'Cannot delete because it has associated records.']);

    // Type still exists
    $this->assertDatabaseHas('transaction_types', ['id' => $type->id]);
});

it('returns 404 when deleting a transaction type belonging to another user', function () {
    Sanctum::actingAs(User::factory()->create());

    $other = User::factory()->create();
    $otherType = TransactionType::withoutGlobalScopes()->where('user_id', $other->id)->first();

    $this->deleteJson("/api/transaction-types/{$otherType->id}")->assertNotFound();
});
