<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'wallet_id' => Wallet::factory(),
            'transaction_category_id' => TransactionCategory::factory(),
            'amount' => fake()->randomFloat(2, 1000, 5000000),
            'name' => fake()->words(3, true),
            'note' => fake()->optional()->sentence(),
            'photo_path' => null,
        ];
    }
}
