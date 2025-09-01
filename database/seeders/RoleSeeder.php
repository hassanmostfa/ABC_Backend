<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\PermissionItem;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Super Admin role
        $superAdmin = Role::create([
            'name' => 'Super Admin',
            'description' => 'Full access to all features and settings',
            'is_active' => true,
        ]);

        // Create Admin role
        $admin = Role::create([
            'name' => 'Admin',
            'description' => 'Administrative access with limited permissions',
            'is_active' => true,
        ]);

        // Create Manager role
        $manager = Role::create([
            'name' => 'Manager',
            'description' => 'Manager access for content and product management',
            'is_active' => true,
        ]);

        // Get all permission items
        $permissionItems = PermissionItem::all();

        // Assign all permissions to Super Admin
        $superAdminPermissions = [];
        foreach ($permissionItems as $item) {
            $superAdminPermissions[$item->id] = [
                'view' => true,
                'add' => true,
                'edit' => true,
                'delete' => true,
            ];
        }
        $superAdmin->assignPermissions($superAdminPermissions);

        // Assign limited permissions to Admin (no user management)
        $adminPermissions = [];
        foreach ($permissionItems as $item) {
            if (!in_array($item->slug, ['users', 'roles', 'permissions'])) {
                $adminPermissions[$item->id] = [
                    'view' => true,
                    'add' => true,
                    'edit' => true,
                    'delete' => false,
                ];
            }
        }
        $admin->assignPermissions($adminPermissions);

        // Assign view and edit permissions to Manager
        $managerPermissions = [];
        foreach ($permissionItems as $item) {
            if (in_array($item->permissionCategory->slug, ['content-management', 'product-management'])) {
                $managerPermissions[$item->id] = [
                    'view' => true,
                    'add' => true,
                    'edit' => true,
                    'delete' => false,
                ];
            }
        }
        $manager->assignPermissions($managerPermissions);
    }
}
