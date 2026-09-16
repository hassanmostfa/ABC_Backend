<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('special_orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number', 50)->unique();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('source', 20)->default('call_center');
            $table->enum('payment_method', ['cash', 'wallet', 'online_link']);
            $table->string('payment_gateway_src', 20)->nullable()->comment('knet, cc');
            $table->json('payload')->comment('Frozen order draft with the special discount already allocated to lines');
            $table->decimal('original_amount_due', 10, 3);
            $table->decimal('final_price', 10, 3);
            $table->decimal('special_discount', 10, 3);
            $table->decimal('discount_percentage', 5, 2);
            $table->enum('status', ['pending', 'approved', 'rejected', 'cancelled'])->default('pending');
            $table->foreignId('requested_by_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->foreignId('reviewed_by_id')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_notes')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->foreignId('order_checkout_id')->nullable()->constrained('order_checkouts')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('special_orders');
    }
};
