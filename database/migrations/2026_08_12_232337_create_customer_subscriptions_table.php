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
        Schema::create('customer_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->foreignId('subscription_id')->constrained('subscriptions')->onDelete('cascade');
            $table->integer('orders_per_month')->comment('Number of orders per month');
            $table->date('start_date')->comment('Subscription start date');
            $table->date('end_date')->comment('Subscription end date');
            $table->enum('status', ['active', 'paused', 'cancelled', 'completed', 'pending_cancellation'])->default('active');
            $table->decimal('total_amount', 10, 3)->default(0);
            $table->integer('total_orders')->default(0)->comment('Total number of orders in this subscription');
            $table->json('metadata')->nullable()->comment('Additional data like payment info, etc.');
            $table->timestamps();
            
            $table->index(['customer_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customer_subscriptions');
    }
};
