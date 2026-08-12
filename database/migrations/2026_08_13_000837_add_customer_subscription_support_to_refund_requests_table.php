<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Make order/invoice nullable for subscription refunds
        DB::statement('ALTER TABLE refund_requests DROP FOREIGN KEY refund_requests_order_id_foreign');
        DB::statement('ALTER TABLE refund_requests DROP FOREIGN KEY refund_requests_invoice_id_foreign');
        DB::statement('ALTER TABLE refund_requests MODIFY order_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE refund_requests MODIFY invoice_id BIGINT UNSIGNED NULL');
        DB::statement('ALTER TABLE refund_requests ADD CONSTRAINT refund_requests_order_id_foreign FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE');
        DB::statement('ALTER TABLE refund_requests ADD CONSTRAINT refund_requests_invoice_id_foreign FOREIGN KEY (invoice_id) REFERENCES invoices(id) ON DELETE CASCADE');

        Schema::table('refund_requests', function (Blueprint $table) {
            $table->foreignId('customer_subscription_id')
                ->nullable()
                ->after('customer_id')
                ->constrained('customer_subscriptions')
                ->nullOnDelete();
        });

        DB::statement("ALTER TABLE customer_subscriptions MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'completed', 'pending_cancellation') NOT NULL DEFAULT 'active'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('refund_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('customer_subscription_id');
        });

        DB::statement("UPDATE customer_subscriptions SET status = 'active' WHERE status = 'pending_cancellation'");
        DB::statement("ALTER TABLE customer_subscriptions MODIFY COLUMN status ENUM('active', 'paused', 'cancelled', 'completed') NOT NULL DEFAULT 'active'");
    }
};
