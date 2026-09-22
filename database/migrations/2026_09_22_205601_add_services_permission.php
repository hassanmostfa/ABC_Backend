<?php

use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $category = PermissionCategory::query()->where('slug', 'settings')->first();
        if (!$category) {
            return;
        }

        $item = PermissionItem::query()->updateOrCreate(
            ['slug' => 'services'],
            [
                'name' => 'Services',
                'permission_category_id' => $category->id,
                'description' => null,
                'sort_order' => 0,
                'is_active' => true,
            ]
        );

        $superAdmin = Role::query()->where('name', 'Super Admin')->first();
        if (!$superAdmin) {
            return;
        }

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

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        PermissionItem::query()->where('slug', 'services')->delete();
    }
};
