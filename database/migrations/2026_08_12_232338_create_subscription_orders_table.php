<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('subscription_orders')) {
            return;
        }

        Schema::create('subscription_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique();
            $table->foreignId('customer_subscription_id')->constrained('customer_subscriptions')->onDelete('cascade');
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->integer('order_sequence')->comment('Order sequence in subscription (1, 2, 3...)');
            $table->integer('month_number')->comment('Month number in subscription (1-12)');
            $table->integer('order_in_month')->comment('Order number within the month (1, 2, etc.)');
            $table->date('scheduled_delivery_date')->comment('Customer selected delivery date');
            $table->enum('status', ['pending', 'processing', 'shipped', 'delivered', 'cancelled'])->default('pending');
            $table->decimal('total_amount', 10, 3)->default(0);
            $table->text('notes')->nullable();
            $table->json('erp_data')->nullable()->comment('ERP order ID and response');
            $table->timestamp('sent_to_erp_at')->nullable();
            $table->timestamps();
            
            $table->index(['customer_subscription_id', 'order_sequence'], 'sub_orders_cust_sub_seq_idx');
            $table->index(['scheduled_delivery_date', 'status'], 'sub_orders_delivery_status_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_orders');
    }
};
