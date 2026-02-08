<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\PermissionCategory;
use App\Models\PermissionItem;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Matches the permissions structure (categories + items). Uses updateOrCreate to add/update.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Categories Managment',
                'slug' => 'categories',
                'description' => null,
                'sort_order' => 0,
                'items' => [
                    ['name' => 'Categories', 'slug' => 'categories', 'sort_order' => 0],
                    ['name' => 'Subcategories', 'slug' => 'subcategories', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Locations',
                'slug' => 'locations',
                'description' => null,
                'sort_order' => 0,
                'items' => [
                    ['name' => 'Countries', 'slug' => 'countries', 'sort_order' => 0],
                    ['name' => 'Governorates', 'slug' => 'governorates', 'sort_order' => 0],
                    ['name' => 'Areas', 'slug' => 'areas', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Orders Managment',
                'slug' => 'orders_management',
                'description' => null,
                'sort_order' => 0,
                'items' => [
                    ['name' => 'Orders', 'slug' => 'orders', 'sort_order' => 0],
                    ['name' => 'Invoices', 'slug' => 'invoices', 'sort_order' => 0],
                    ['name' => 'Transactions', 'slug' => 'transactions', 'sort_order' => 0],
                    ['name' => 'Refund Requests', 'slug' => 'refund_requests', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'Admin Managment',
                'slug' => 'admin-management',
                'description' => null,
                'sort_order' => 1,
                'items' => [
                    ['name' => 'Careers', 'slug' => 'careers', 'sort_order' => 0],
                    ['name' => 'Admins', 'slug' => 'admins', 'sort_order' => 1],
                    ['name' => 'Roles', 'slug' => 'roles', 'sort_order' => 2],
                    ['name' => 'Permissions', 'slug' => 'permissions', 'sort_order' => 3],
                ],
            ],
            [
                'name' => 'Product Management',
                'slug' => 'product-management',
                'description' => 'Manage products, brands, variants, and product types',
                'sort_order' => 3,
                'items' => [
                    ['name' => 'Offers', 'slug' => 'offers', 'sort_order' => 0],
                    ['name' => 'Products', 'slug' => 'products', 'sort_order' => 1],
                ],
            ],
            [
                'name' => 'Settings Managment',
                'slug' => 'settings',
                'description' => 'Manage general and platform settings',
                'sort_order' => 4,
                'items' => [
                    ['name' => 'Contact_Us', 'slug' => 'contact_us', 'sort_order' => 0],
                    ['name' => 'Social Media Links', 'slug' => 'social_media_links', 'sort_order' => 0],
                    ['name' => 'General Settings', 'slug' => 'settings', 'sort_order' => 0],
                    ['name' => 'Team Members', 'slug' => 'team_members', 'sort_order' => 0],
                    ['name' => 'Activity Logs', 'slug' => 'activity_logs', 'sort_order' => 0],
                    ['name' => 'Notifications', 'slug' => 'notifications', 'sort_order' => 0],
                    ['name' => 'Sliders', 'slug' => 'sliders', 'sort_order' => 0],
                    ['name' => 'FAQs', 'slug' => 'faqs', 'sort_order' => 0],
                ],
            ],
            [
                'name' => 'User Management',
                'slug' => 'user-management',
                'description' => 'Manage users, roles, and permissions',
                'sort_order' => 5,
                'items' => [
                    ['name' => 'Charities', 'slug' => 'charities', 'sort_order' => 0],
                    ['name' => 'Customers', 'slug' => 'customers', 'sort_order' => 1],
                    ['name' => 'Points Transactions', 'slug' => 'points_transactions', 'sort_order' => 2],
                ],
            ],
        ];

        foreach ($categories as $categoryData) {
            $items = $categoryData['items'];
            unset($categoryData['items']);

            $category = PermissionCategory::updateOrCreate(
                ['slug' => $categoryData['slug']],
                array_merge($categoryData, ['is_active' => true])
            );

            foreach ($items as $itemData) {
                PermissionItem::updateOrCreate(
                    ['slug' => $itemData['slug']],
                    array_merge($itemData, [
                        'permission_category_id' => $category->id,
                        'description' => null,
                        'is_active' => true,
                    ])
                );
            }
        }
    }
}
