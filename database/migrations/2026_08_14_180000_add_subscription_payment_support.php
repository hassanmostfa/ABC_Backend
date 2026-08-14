<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE invoices DROP FOREIGN KEY invoices_order_id_foreign');
        DB::statement('ALTER TABLE invoices MODIFY order_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL');

        Schema::table('invoices', function (Blueprint $table) {
            $table->foreignId('customer_subscription_id')
                ->nullable()
                ->after('order_id')
                ->constrained('customer_subscriptions')
                ->nullOnDelete();
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("ALTER TABLE customer_subscriptions MODIFY COLUMN status ENUM('pending_payment', 'active', 'paused', 'cancelled', 'completed', 'pending_cancellation') NOT NULL DEFAULT 'active'");
            DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('order', 'wallet_charge', 'order_checkout', 'subscription') NOT NULL DEFAULT 'order'");
        }
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_subscription_id');
        });

        DB::statement('ALTER TABLE invoices DROP FOREIGN KEY invoices_order_id_foreign');
        DB::statement('ALTER TABLE invoices MODIFY order_id BIGINT UNSIGNED NOT NULL');
        DB::statement('ALTER TABLE invoices ADD CONSTRAINT invoices_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE');

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement("UPDATE customer_subscriptions SET status = 'active' WHERE status = 'pending_payment'");
            DB::statement("ALTER TABLE customer_subscriptions MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'completed', 'pending_cancellation') NOT NULL DEFAULT 'active'");
            DB::statement("UPDATE payments SET type = 'order' WHERE type = 'subscription'");
            DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('order', 'wallet_charge', 'order_checkout') NOT NULL DEFAULT 'order'");
        }
    }
};
