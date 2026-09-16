<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\PermissionItem;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Creates/updates Super Admin plus the Special Orders Approver role.
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

        $this->seedSpecialOrdersApprover($permissionItems);
    }

    /**
     * Reviews discounted call-center orders. Can read special orders and orders, and approve or
     * reject them, but cannot create them.
     *
     * @param  \Illuminate\Support\Collection<int, PermissionItem>  $permissionItems
     */
    private function seedSpecialOrdersApprover($permissionItems): void
    {
        $approver = Role::updateOrCreate(
            ['name' => 'Special Orders Approver'],
            [
                'description' => 'Approves or rejects special orders created by the call center',
                'is_active' => true,
            ]
        );

        $grants = [
            'special_orders' => ['view' => true],
            'special_order_approvals' => ['view' => true, 'edit' => true],
            'orders' => ['view' => true],
        ];

        $permissions = [];
        foreach ($grants as $slug => $actions) {
            $item = $permissionItems->firstWhere('slug', $slug);
            if ($item) {
                $permissions[$item->id] = $actions;
            }
        }

        $approver->assignPermissions($permissions);
    }
}
