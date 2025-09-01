<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\PermissionCategory;
use App\Models\PermissionItem;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Content Management',
                'slug' => 'content-management',
                'description' => 'Manage website content including banners, languages, and translations',
                'sort_order' => 1,
                'items' => [
                    ['name' => 'Banners', 'slug' => 'banners', 'sort_order' => 1],
                    ['name' => 'Languages', 'slug' => 'languages', 'sort_order' => 2],
                    ['name' => 'Translations', 'slug' => 'translations', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'Pharmacy Management',
                'slug' => 'pharmacy-management',
                'description' => 'Manage pharmacies, branches, and revenue sharing',
                'sort_order' => 2,
                'items' => [
                    ['name' => 'Pharmacies', 'slug' => 'pharmacies', 'sort_order' => 1],
                    ['name' => 'Branches', 'slug' => 'branches', 'sort_order' => 2],
                    ['name' => 'Revenue Sharing', 'slug' => 'revenue-sharing', 'sort_order' => 3],
                ]
            ],
            [
                'name' => 'Product Management',
                'slug' => 'product-management',
                'description' => 'Manage products, brands, variants, and product types',
                'sort_order' => 3,
                'items' => [
                    ['name' => 'Products', 'slug' => 'products', 'sort_order' => 1],
                    ['name' => 'Brands', 'slug' => 'brands', 'sort_order' => 2],
                    ['name' => 'Variants', 'slug' => 'variants', 'sort_order' => 3],
                    ['name' => 'Product Types', 'slug' => 'product-types', 'sort_order' => 4],
                ]
            ],
            [
                'name' => 'Settings',
                'slug' => 'settings',
                'description' => 'Manage general and platform settings',
                'sort_order' => 4,
                'items' => [
                    ['name' => 'General Settings', 'slug' => 'general-settings', 'sort_order' => 1],
                    ['name' => 'Platform Settings', 'slug' => 'platform-settings', 'sort_order' => 2],
                ]
            ],
            [
                'name' => 'User Management',
                'slug' => 'user-management',
                'description' => 'Manage users, roles, and permissions',
                'sort_order' => 5,
                'items' => [
                    ['name' => 'Users', 'slug' => 'users', 'sort_order' => 1],
                    ['name' => 'Roles', 'slug' => 'roles', 'sort_order' => 2],
                    ['name' => 'Permissions', 'slug' => 'permissions', 'sort_order' => 3],
                ]
            ],
        ];

        foreach ($categories as $categoryData) {
            $items = $categoryData['items'];
            unset($categoryData['items']);
            
            $category = PermissionCategory::create($categoryData);
            
            foreach ($items as $itemData) {
                $category->permissionItems()->create($itemData);
            }
        }
    }
}
