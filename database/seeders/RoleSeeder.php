<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\PermissionItem;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates/updates Super Admin role only and assigns all permissions to it.
     */
    public function run(): void
    {
        $superAdmin = Role::updateOrCreate(
            ['name' => 'Super Admin'],
            [
                'description' => 'Full access to all features and settings',
                'is_active' => true,
            ]
        );

        $permissionItems = PermissionItem::all();

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
    }
}
