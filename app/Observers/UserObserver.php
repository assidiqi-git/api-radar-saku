<?php

namespace App\Observers;

use App\Models\TransactionType;
use App\Models\User;
use App\Models\Wallet;

class UserObserver
{
    /**
     * Handle the User "created" event.
     * Seeds default TransactionTypes and a default Wallet for every new user.
     */
    public function created(User $user): void
    {
        $defaultTypes = ['income', 'outcome', 'saving'];

        foreach ($defaultTypes as $type) {
            TransactionType::withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'name' => $type,
                'description' => null,
            ]);
        }

        Wallet::withoutGlobalScopes()->create([
            'user_id' => $user->id,
            'name' => 'Dompet Utama',
            'type' => 'cash',
            'balance' => 0,
        ]);
    }
}
