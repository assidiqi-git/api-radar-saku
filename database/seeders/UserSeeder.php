<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Seed the users table.
     * UserObserver will automatically create 3 default TransactionTypes
     * (income, outcome, saving) and 1 default Wallet ("Dompet Utama") per user.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@example.com',
        ]);
    }
}
