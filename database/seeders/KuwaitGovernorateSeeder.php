<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Governorate;

class KuwaitGovernorateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $governorates = [
            [
                'name_en' => 'Al Ahmadi',
                'name_ar' => 'الأحمدي',
                'country_id' => 1,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Farwaniyah',
                'name_ar' => 'الفروانية',
                'country_id' => 1,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Jahra',
                'name_ar' => 'الجهراء',
                'country_id' => 1,
                'is_active' => true,
            ],
            [
                'name_en' => 'Capital',
                'name_ar' => 'العاصمة',
                'country_id' => 1,
                'is_active' => true,
            ],
            [
                'name_en' => 'Hawalli',
                'name_ar' => 'حولي',
                'country_id' => 1,
                'is_active' => true,
            ],
            [
                'name_en' => 'Mubarak Al-Kabeer',
                'name_ar' => 'مبارك الكبير',
                'country_id' => 1,
                'is_active' => true,
            ],
        ];

        foreach ($governorates as $governorate) {
            Governorate::updateOrCreate(
                ['name_en' => $governorate['name_en'], 'country_id' => $governorate['country_id']],
                $governorate
            );
        }
    }
}