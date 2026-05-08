<?php

namespace Database\Seeders;

use App\Models\TransactionCategory;
use App\Models\TransactionType;
use App\Models\User;
use Illuminate\Database\Seeder;

class TransactionCategorySeeder extends Seeder
{
    /**
     * Seed realistic transaction categories per TransactionType for each user.
     */
    public function run(): void
    {
        /** @var array<string, list<array{name: string, description: string}>> */
        $categoriesByType = [
            'income' => [
                ['name' => 'Gaji', 'description' => 'Pendapatan gaji bulanan'],
                ['name' => 'Freelance', 'description' => 'Pendapatan dari pekerjaan lepas'],
                ['name' => 'Bonus', 'description' => 'Bonus dan insentif'],
                ['name' => 'Investasi', 'description' => 'Pendapatan dari investasi'],
            ],
            'outcome' => [
                ['name' => 'Makan & Minum', 'description' => 'Pengeluaran makanan dan minuman'],
                ['name' => 'Transportasi', 'description' => 'Biaya transportasi harian'],
                ['name' => 'Belanja', 'description' => 'Belanja kebutuhan sehari-hari'],
                ['name' => 'Tagihan', 'description' => 'Tagihan listrik, air, internet'],
                ['name' => 'Hiburan', 'description' => 'Hiburan dan rekreasi'],
                ['name' => 'Kesehatan', 'description' => 'Biaya kesehatan dan obat-obatan'],
            ],
            'saving' => [
                ['name' => 'Tabungan Darurat', 'description' => 'Dana darurat'],
                ['name' => 'Dana Liburan', 'description' => 'Tabungan untuk liburan'],
                ['name' => 'Dana Pendidikan', 'description' => 'Tabungan untuk pendidikan'],
            ],
        ];

        User::all()->each(function (User $user) use ($categoriesByType) {
            $transactionTypes = TransactionType::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('name');

            foreach ($categoriesByType as $typeName => $categories) {
                $type = $transactionTypes->get($typeName);

                if (! $type) {
                    continue;
                }

                foreach ($categories as $category) {
                    TransactionCategory::withoutGlobalScopes()->create([
                        'user_id' => $user->id,
                        'transaction_type_id' => $type->id,
                        ...$category,
                    ]);
                }
            }
        });
    }
}
