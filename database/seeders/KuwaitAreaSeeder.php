<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Area;
use App\Models\Governorate;

class KuwaitAreaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get governorate IDs
        $governorates = Governorate::where('country_id', 1)->get()->keyBy('name_en');
        
        $areas = [
            // Al Ahmadi Governorate Areas
            [
                'name_en' => 'Ahmadi',
                'name_ar' => 'الأحمدي',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Fahaheel',
                'name_ar' => 'الفحيحيل',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Mangaf',
                'name_ar' => 'المنقف',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Abu Halifa',
                'name_ar' => 'أبو حليفة',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Fintas',
                'name_ar' => 'الفحيحيل',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Mahboula',
                'name_ar' => 'المهبولة',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Wafra',
                'name_ar' => 'الوفرة',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Zour',
                'name_ar' => 'الزور',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Sabah Al Ahmad',
                'name_ar' => 'صباح الأحمد',
                'governorate_id' => $governorates['Al Ahmadi']->id,
                'is_active' => true,
            ],

            // Al Farwaniyah Governorate Areas
            [
                'name_en' => 'Farwaniyah',
                'name_ar' => 'الفروانية',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Abraq Khaitan',
                'name_ar' => 'أبرق خيطان',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Andalus',
                'name_ar' => 'الأندلس',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Ardhiya',
                'name_ar' => 'العارضية',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Rabiya',
                'name_ar' => 'الرابية',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Rigga',
                'name_ar' => 'الرقة',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Shadadiya',
                'name_ar' => 'الشدادية',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Shamiya',
                'name_ar' => 'الشمية',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Jleeb Al Shuyoukh',
                'name_ar' => 'جليب الشيوخ',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Khaitan',
                'name_ar' => 'خيطان',
                'governorate_id' => $governorates['Al Farwaniyah']->id,
                'is_active' => true,
            ],

            // Al Jahra Governorate Areas
            [
                'name_en' => 'Jahra',
                'name_ar' => 'الجهراء',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Qasr',
                'name_ar' => 'القصر',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Sulaibiya',
                'name_ar' => 'الصليبية',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Waha',
                'name_ar' => 'الواحة',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Naeem',
                'name_ar' => 'نعيم',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Qasr',
                'name_ar' => 'قصر',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Saad Al Abdullah',
                'name_ar' => 'سعد العبدالله',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Taima',
                'name_ar' => 'تيماء',
                'governorate_id' => $governorates['Al Jahra']->id,
                'is_active' => true,
            ],

            // Capital Governorate Areas
            [
                'name_en' => 'Kuwait City',
                'name_ar' => 'مدينة الكويت',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Dasman',
                'name_ar' => 'دسمان',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Dasma',
                'name_ar' => 'دسمة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Doha',
                'name_ar' => 'الدوحة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Jaber Al Ahmad',
                'name_ar' => 'جابر الأحمد',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Kaifan',
                'name_ar' => 'كيفان',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Khaldiya',
                'name_ar' => 'الخالدية',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Kuwait Free Trade Zone',
                'name_ar' => 'المنطقة الحرة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Mishref',
                'name_ar' => 'مشرف',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Nuzha',
                'name_ar' => 'النزهة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Qadsiya',
                'name_ar' => 'القدسية',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Qurtuba',
                'name_ar' => 'قرطبة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Rawda',
                'name_ar' => 'الروضة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Sharq',
                'name_ar' => 'الشرق',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Shuwaikh',
                'name_ar' => 'الشويخ',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Sulaibikhat',
                'name_ar' => 'الصليبيخات',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Surra',
                'name_ar' => 'السرة',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Yarmouk',
                'name_ar' => 'اليرموك',
                'governorate_id' => $governorates['Capital']->id,
                'is_active' => true,
            ],

            // Hawalli Governorate Areas
            [
                'name_en' => 'Hawalli',
                'name_ar' => 'حولي',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Bayan',
                'name_ar' => 'البيان',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Mansouriya',
                'name_ar' => 'المنصورية',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Salmiya',
                'name_ar' => 'السالمية',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Shaab',
                'name_ar' => 'الشعب',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Shaab Leisure Park',
                'name_ar' => 'منتزه الشعب',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Shuhada',
                'name_ar' => 'الشهداء',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Zahar',
                'name_ar' => 'الزهر',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Bayan',
                'name_ar' => 'البيان',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Hitteen',
                'name_ar' => 'حطين',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Jabriya',
                'name_ar' => 'الجابرية',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Maidan Hawalli',
                'name_ar' => 'ميدان حولي',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Mishref',
                'name_ar' => 'مشرف',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Mubarak Al-Abdullah',
                'name_ar' => 'مبارك العبدالله',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Rumaithiya',
                'name_ar' => 'الرميثية',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Salwa',
                'name_ar' => 'سلوى',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Shaab',
                'name_ar' => 'الشعب',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Zahra',
                'name_ar' => 'الزهرة',
                'governorate_id' => $governorates['Hawalli']->id,
                'is_active' => true,
            ],

            // Mubarak Al-Kabeer Governorate Areas
            [
                'name_en' => 'Mubarak Al-Kabeer',
                'name_ar' => 'مبارك الكبير',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Abu Al Hasaniya',
                'name_ar' => 'أبو الحصانية',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Adan',
                'name_ar' => 'عدان',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Masayel',
                'name_ar' => 'المسايل',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Qurain',
                'name_ar' => 'القرين',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Al Qurayn',
                'name_ar' => 'القرين',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Fnaitees',
                'name_ar' => 'الفنيطيس',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Messila',
                'name_ar' => 'المسيلة',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Qusour',
                'name_ar' => 'القصور',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Sabah Al Salem',
                'name_ar' => 'صباح السالم',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
            [
                'name_en' => 'Sabahiya',
                'name_ar' => 'الصباحية',
                'governorate_id' => $governorates['Mubarak Al-Kabeer']->id,
                'is_active' => true,
            ],
        ];

        foreach ($areas as $area) {
            Area::updateOrCreate(
                ['name_en' => $area['name_en'], 'governorate_id' => $area['governorate_id']],
                $area
            );
        }
    }
}