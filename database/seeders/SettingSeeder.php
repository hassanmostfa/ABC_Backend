<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Setting;
use App\Models\SettingTranslation;

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

        // Translatable settings with default content
        $translatableSettings = [
            'about' => [
                'en' => "Welcome to our application.\n\nWe are committed to providing you with the best experience. Our mission is to deliver quality products and excellent service to our customers.\n\nThank you for choosing us.",
                'ar' => "مرحباً بكم في تطبيقنا.\n\nنحن ملتزمون بتقديم أفضل تجربة لكم. مهمتنا هي تقديم منتجات عالية الجودة وخدمة ممتازة لعملائنا.\n\nشكراً لاختياركم لنا.",
            ],
            'terms_and_conditions' => [
                'en' => "Terms and Conditions\n\n1. Acceptance of Terms\nBy using our application, you agree to these terms and conditions.\n\n2. Use of Service\nYou must use the service in compliance with applicable laws and regulations.\n\n3. Privacy\nYour privacy is important to us. Please review our privacy policy for more information.\n\n4. Changes\nWe reserve the right to modify these terms at any time. Continued use of the service constitutes acceptance of changes.",
                'ar' => "الشروط والأحكام\n\n1. قبول الشروط\nباستخدام تطبيقنا، فإنك توافق على هذه الشروط والأحكام.\n\n2. استخدام الخدمة\nيجب عليك استخدام الخدمة بما يتوافق مع القوانين واللوائح المعمول بها.\n\n3. الخصوصية\nخصوصيتك مهمة بالنسبة لنا. يرجى مراجعة سياسة الخصوصية لمزيد من المعلومات.\n\n4. التغييرات\nنحتفظ بالحق في تعديل هذه الشروط في أي وقت. يشكل الاستمرار في استخدام الخدمة قبولاً للتغييرات.",
            ],
        ];

        foreach ($translatableSettings as $key => $translations) {
            $setting = Setting::updateOrCreate(
                ['key' => $key],
                ['value' => null]
            );

            foreach ($translations as $locale => $value) {
                SettingTranslation::updateOrCreate(
                    ['setting_id' => $setting->id, 'locale' => $locale],
                    ['value' => $value]
                );
            }
        }
    }
}
