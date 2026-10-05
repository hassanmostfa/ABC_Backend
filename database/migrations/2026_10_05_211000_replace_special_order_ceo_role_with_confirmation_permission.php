<?php

use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $ceoPermission = PermissionItem::query()->where('slug', 'special_order_ceo_approvals')->first();
        if ($ceoPermission) {
            DB::table('role_permissions')->where('permission_item_id', $ceoPermission->id)->delete();
            $ceoPermission->delete();
        }

        $ceoRole = Role::query()->where('name', 'Special Orders CEO')->first();
        if ($ceoRole) {
            DB::table('role_permissions')->where('role_id', $ceoRole->id)->delete();
            DB::table('admins')->where('role_id', $ceoRole->id)->update(['role_id' => null]);
            $ceoRole->delete();
        }

        Role::query()->where('name', 'Special Orders Approver')->update([
            'description' => 'Approves or rejects special orders created by the call center',
        ]);

        $category = PermissionCategory::query()->where('slug', 'orders_management')->first();
        if (!$category) {
            return;
        }

        $item = PermissionItem::query()->updateOrCreate(
            ['slug' => 'special_order_confirmations'],
            [
                'name' => 'Special Order Confirmations',
                'permission_category_id' => $category->id,
                'description' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        $superAdmin = Role::query()->where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->permissions()->updateOrCreate(
                ['permission_item_id' => $item->id],
                [
                    'can_view' => true,
                    'can_add' => true,
                    'can_edit' => true,
                    'can_delete' => true,
                ]
            );
        }
    }

    public function down(): void
    {
        $item = PermissionItem::query()->where('slug', 'special_order_confirmations')->first();
        if ($item) {
            DB::table('role_permissions')->where('permission_item_id', $item->id)->delete();
            $item->delete();
        }
    }
};
