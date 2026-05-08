<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class WalletSeeder extends Seeder
{
    /**
     * Seed additional wallets for each user.
     * Each user already has "Dompet Utama" from UserObserver.
     */
    public function run(): void
    {
        $additionalWallets = [
            ['name' => 'Rekening BCA', 'type' => 'checking', 'balance' => 5000000],
            ['name' => 'Rekening Mandiri', 'type' => 'savings', 'balance' => 15000000],
            ['name' => 'GoPay', 'type' => 'cash', 'balance' => 250000],
        ];

        User::all()->each(function (User $user) use ($additionalWallets) {
            foreach ($additionalWallets as $wallet) {
                Wallet::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    ...$wallet,
                ]);
            }
        });
    }
}
