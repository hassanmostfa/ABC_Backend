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
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                ['key' => $setting['key']],
                ['value' => $setting['value']]
            );
        }
    }
}
