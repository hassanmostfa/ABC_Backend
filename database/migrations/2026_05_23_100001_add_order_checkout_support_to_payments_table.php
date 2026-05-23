<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('order_checkout_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('order_checkouts')
                ->nullOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('order', 'wallet_charge', 'order_checkout') NOT NULL DEFAULT 'order'");
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['order_checkout_id']);
            $table->dropColumn('order_checkout_id');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('order', 'wallet_charge') NOT NULL DEFAULT 'order'");
        }
    }
};
