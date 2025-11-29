<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;

class SettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            [
                'key' => 'one_point_dicount',
                'value' => '0.1', // 1 point = 0.1 dinar (10 points = 1 dinar)
            ],
            [
                'key' => 'tax',
                'value' => '0.15', // Tax rate (15%)
            ],
            [
                'key' => 'otp_test_code',
                'value' => '1234', // OTP test code for development/testing
            ],
            [
                'key' => 'is_production',
                'value' => '0', // 0 = false (development), 1 = true (production)
            ],
            // Delivery Settings
            [
                'key' => 'delivery_price',
                'value' => '3', // Delivery price
            ],
            [
                'key' => 'minimum_home_order',
                'value' => '5', // Minimum home order amount
            ],
            [
                'key' => 'minimum_charity_order',
                'value' => '13', // Minimum charity order amount
            ],
            [
                'key' => 'opening_time',
                'value' => '10:00 am', // Opening time
            ],
            [
                'key' => 'closing_time',
                'value' => '10:00 pm', // Closing time
            ],
            [
                'key' => 'delivery_days',
                'value' => '7', // Delivery days
            ],
            [
                'key' => 'slot_interval',
                'value' => '720', // Slot interval (in minutes)
            ],
            [
                'key' => 'max_delivery_per_slot',
                'value' => '999', // Maximum delivery per slot
            ],
            [
                'key' => 'day_offs',
                'value' => '[]', // Day offs (JSON array, e.g., ["saturday", "sunday"])
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
