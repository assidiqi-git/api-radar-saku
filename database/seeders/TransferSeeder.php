<?php

namespace Database\Seeders;

use App\Models\Transfer;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class TransferSeeder extends Seeder
{
    /**
     * Seed transfers between wallets for each user.
     * Ensures from_wallet and to_wallet are different.
     */
    public function run(): void
    {
        $transferTemplates = [
            ['note' => 'Top up GoPay', 'amount' => 200000, 'fee' => 0],
            ['note' => 'Transfer ke tabungan', 'amount' => 1000000, 'fee' => 0],
            ['note' => 'Tarik tunai ATM', 'amount' => 500000, 'fee' => 5000],
            ['note' => 'Transfer antar bank', 'amount' => 2000000, 'fee' => 6500],
        ];

        User::all()->each(function (User $user) use ($transferTemplates) {
            $wallets = Wallet::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->get();

            if ($wallets->count() < 2) {
                return;
            }

            foreach ($transferTemplates as $template) {
                $fromWallet = $wallets->random();
                $toWallet = $wallets->except($fromWallet->id)->random();

                Transfer::withoutGlobalScopes()->create([
                    'user_id' => $user->id,
                    'from_wallet_id' => $fromWallet->id,
                    'to_wallet_id' => $toWallet->id,
                    'amount' => $template['amount'],
                    'fee' => $template['fee'],
                    'note' => $template['note'],
                    'transfer_date' => fake()->dateTimeBetween('-3 months', 'now'),
                ]);
            }
        });
    }
}
