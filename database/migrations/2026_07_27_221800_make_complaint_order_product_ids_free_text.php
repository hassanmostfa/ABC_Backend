<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        // Free-text references: may not exist as orders/products in this system.
        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'order_id')) {
                $table->string('order_id', 100)->nullable()->change();
            }
            if (Schema::hasColumn('complaints', 'product_id')) {
                $table->string('product_id', 100)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('complaints')) {
            return;
        }

        // Best-effort revert: cast numeric strings back to bigint, clear non-numeric.
        if (Schema::hasColumn('complaints', 'order_id')) {
            DB::table('complaints')
                ->whereNotNull('order_id')
                ->whereRaw("order_id NOT REGEXP '^[0-9]+$'")
                ->update(['order_id' => null]);
        }
        if (Schema::hasColumn('complaints', 'product_id')) {
            DB::table('complaints')
                ->whereNotNull('product_id')
                ->whereRaw("product_id NOT REGEXP '^[0-9]+$'")
                ->update(['product_id' => null]);
        }

        Schema::table('complaints', function (Blueprint $table) {
            if (Schema::hasColumn('complaints', 'order_id')) {
                $table->unsignedBigInteger('order_id')->nullable()->change();
            }
            if (Schema::hasColumn('complaints', 'product_id')) {
                $table->unsignedBigInteger('product_id')->nullable()->change();
            }
        });
    }
};
