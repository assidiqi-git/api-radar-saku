<?php

namespace Database\Seeders;

use App\Models\Transaction;
use App\Models\TransactionCategory;
use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Seed realistic transactions for each user.
     * Amount ranges are tailored to each category for realism.
     */
    public function run(): void
    {
        /** @var array<string, array{min: int, max: int, count: int}> */
        $categoryConfig = [
            'Gaji' => ['min' => 5000000, 'max' => 15000000, 'count' => 3],
            'Freelance' => ['min' => 500000, 'max' => 5000000, 'count' => 2],
            'Bonus' => ['min' => 1000000, 'max' => 5000000, 'count' => 1],
            'Investasi' => ['min' => 100000, 'max' => 2000000, 'count' => 1],
            'Makan & Minum' => ['min' => 15000, 'max' => 150000, 'count' => 5],
            'Transportasi' => ['min' => 10000, 'max' => 100000, 'count' => 3],
            'Belanja' => ['min' => 50000, 'max' => 500000, 'count' => 2],
            'Tagihan' => ['min' => 100000, 'max' => 1000000, 'count' => 2],
            'Hiburan' => ['min' => 50000, 'max' => 300000, 'count' => 1],
            'Kesehatan' => ['min' => 50000, 'max' => 500000, 'count' => 1],
            'Tabungan Darurat' => ['min' => 500000, 'max' => 2000000, 'count' => 1],
            'Dana Liburan' => ['min' => 200000, 'max' => 1000000, 'count' => 1],
            'Dana Pendidikan' => ['min' => 300000, 'max' => 1500000, 'count' => 1],
        ];

        /** @var array<string, list<string>> */
        $transactionNames = [
            'Gaji' => ['Gaji Bulanan', 'Gaji Pokok', 'Gaji Lembur'],
            'Freelance' => ['Project Web', 'Desain Logo'],
            'Bonus' => ['Bonus Tahunan'],
            'Investasi' => ['Dividen Saham'],
            'Makan & Minum' => ['Makan Siang', 'Kopi Pagi', 'Makan Malam', 'Jajan Sore', 'Sarapan'],
            'Transportasi' => ['Bensin Motor', 'Grab ke Kantor', 'Parkir Mall'],
            'Belanja' => ['Belanja Bulanan', 'Beli Baju'],
            'Tagihan' => ['Listrik Bulan Ini', 'Internet Bulanan'],
            'Hiburan' => ['Nonton Bioskop'],
            'Kesehatan' => ['Beli Obat'],
            'Tabungan Darurat' => ['Setoran Dana Darurat'],
            'Dana Liburan' => ['Nabung Liburan'],
            'Dana Pendidikan' => ['Nabung Pendidikan'],
        ];

        User::all()->each(function (User $user) use ($categoryConfig, $transactionNames) {
            $wallets = Wallet::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->get();

            $categories = TransactionCategory::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->get()
                ->keyBy('name');

            foreach ($categoryConfig as $categoryName => $config) {
                $category = $categories->get($categoryName);

                if (! $category) {
                    continue;
                }

                $names = $transactionNames[$categoryName] ?? ['Transaksi'];

                for ($i = 0; $i < $config['count']; $i++) {
                    Transaction::withoutGlobalScopes()->create([
                        'user_id' => $user->id,
                        'wallet_id' => $wallets->random()->id,
                        'transaction_category_id' => $category->id,
                        'amount' => fake()->numberBetween($config['min'], $config['max']),
                        'name' => $names[$i % count($names)],
                        'note' => fake()->optional(0.5)->sentence(),
                        'photo_path' => null,
                        'created_at' => fake()->dateTimeBetween('-3 months', 'now'),
                    ]);
                }
            }
        });
    }
}
