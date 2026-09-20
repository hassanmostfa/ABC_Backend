<?php

namespace Database\Seeders;

use App\Models\WalletChargeOffer;
use Illuminate\Database\Seeder;

class WalletChargeOfferSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $offers = [
            [
                'charge_amount' => 10,
                'get_amount' => 15,
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'charge_amount' => 50,
                'get_amount' => 70,
                'sort_order' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($offers as $offer) {
            WalletChargeOffer::updateOrCreate(
                ['charge_amount' => $offer['charge_amount']],
                $offer
            );
        }
    }
}
