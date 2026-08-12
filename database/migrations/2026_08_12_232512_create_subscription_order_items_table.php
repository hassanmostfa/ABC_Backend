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
        Schema::create('subscription_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_order_id')->constrained('subscription_orders')->onDelete('cascade');
            $table->foreignId('product_id')->constrained('products')->onDelete('cascade');
            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->onDelete('cascade');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 3);
            $table->decimal('total_price', 10, 3);
            $table->enum('type', ['condition', 'reward'])->comment('Item from offer condition or reward');
            $table->timestamps();
            
            $table->index('subscription_order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscription_order_items');
    }
};
