<?php

namespace Database\Factories;

use App\Enums\TransactionAction;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TransactionType>
 */
class TransactionTypeFactory extends Factory
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
            'name' => fake()->randomElement(['income', 'outcome', 'saving']),
            'action' => TransactionAction::Neutral,
            'description' => fake()->optional()->sentence(),
        ];
    }
}
