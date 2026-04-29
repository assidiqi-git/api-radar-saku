<?php

namespace Database\Factories;

use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
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
            'from_wallet_id' => Wallet::factory(),
            'to_wallet_id' => Wallet::factory(),
            'amount' => fake()->randomFloat(2, 1000, 1000000),
            'transfer_date' => fake()->date(),
            'fee' => fake()->randomFloat(2, 0, 50000),
            'note' => fake()->optional()->sentence(),
        ];
    }
}
