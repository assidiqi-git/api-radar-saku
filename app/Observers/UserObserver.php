<?php

namespace App\Observers;

use App\Enums\TransactionAction;
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
        $defaultTypes = [
            ['name' => 'income', 'action' => TransactionAction::Addition],
            ['name' => 'outcome', 'action' => TransactionAction::Deduction],
            ['name' => 'saving', 'action' => TransactionAction::Deduction],
        ];

        foreach ($defaultTypes as $type) {
            TransactionType::withoutGlobalScopes()->create([
                'user_id' => $user->id,
                'name' => $type['name'],
                'action' => $type['action'],
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
