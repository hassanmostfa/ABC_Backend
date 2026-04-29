<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Charity;

class CharitySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $charities = [
            [
                'name_en' => 'Kuwait Red Crescent Society',
                'name_ar' => 'جمعية الهلال الأحمر الكويتي',
                'phone' => '+965 2245 5555',
            ],
            [
                'name_en' => 'Kuwait Society for Relief',
                'name_ar' => 'جمعية الكويت للإغاثة',
                'phone' => '+965 2245 6666',
            ],
            [
                'name_en' => 'Direct Aid Society',
                'name_ar' => 'جمعية العون المباشر',
                'phone' => '+965 2245 7777',
            ],
            [
                'name_en' => 'Kuwait Charity Organization',
                'name_ar' => 'مؤسسة الكويت الخيرية',
                'phone' => '+965 2245 8888',
            ],
            [
                'name_en' => 'Kuwait Zakat House',
                'name_ar' => 'بيت الزكاة الكويتي',
                'phone' => '+965 2245 9999',
            ],
            [
                'name_en' => 'Kuwait Relief Society',
                'name_ar' => 'جمعية الإغاثة الكويتية',
                'phone' => '+965 2245 1111',
            ],
            [
                'name_en' => 'Kuwait Social Work Society',
                'name_ar' => 'جمعية العمل الاجتماعي الكويتية',
                'phone' => '+965 2245 2222',
            ],
            [
                'name_en' => 'Kuwait Humanitarian Foundation',
                'name_ar' => 'مؤسسة الكويت الإنسانية',
                'phone' => '+965 2245 3333',
            ],
            [
                'name_en' => 'Kuwait Orphans Care Society',
                'name_ar' => 'جمعية رعاية الأيتام الكويتية',
                'phone' => '+965 2245 4444',
            ],
            [
                'name_en' => 'Kuwait Medical Relief Society',
                'name_ar' => 'جمعية الإغاثة الطبية الكويتية',
                'phone' => '+965 2245 5555',
            ],
        ];

        foreach ($charities as $charity) {
            Charity::create($charity);
        }
    }
}
