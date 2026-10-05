<?php

use App\Models\PermissionCategory;
use App\Models\PermissionItem;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('special_orders', function (Blueprint $table) {
            $table->foreignId('confirmed_by_id')
                ->nullable()
                ->after('requested_by_id')
                ->constrained('admins')
                ->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable()->after('confirmed_by_id');
            $table->text('confirmation_notes')->nullable()->after('confirmed_at');
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE special_orders MODIFY COLUMN status ENUM('pending', 'manager_confirmed', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

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
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE special_orders SET status = 'pending' WHERE status = 'manager_confirmed'");
            DB::statement("ALTER TABLE special_orders MODIFY COLUMN status ENUM('pending', 'approved', 'rejected', 'cancelled') NOT NULL DEFAULT 'pending'");
        }

        Schema::table('special_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('confirmed_by_id');
            $table->dropColumn(['confirmed_at', 'confirmation_notes']);
        });

        $item = PermissionItem::query()->where('slug', 'special_order_confirmations')->first();
        if ($item) {
            DB::table('role_permissions')->where('permission_item_id', $item->id)->delete();
            $item->delete();
        }
    }
};
