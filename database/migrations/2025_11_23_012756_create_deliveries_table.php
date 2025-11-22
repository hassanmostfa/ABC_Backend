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
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('orders')->onDelete('cascade');
            $table->enum('payment_method', ['cash', 'card', 'online', 'bank_transfer', 'wallet'])->default('cash');
            $table->text('delivery_address');
            $table->string('block')->nullable();
            $table->string('street')->nullable();
            $table->string('house_number')->nullable();
            $table->datetime('delivery_datetime')->nullable();
            $table->datetime('received_datetime')->nullable();
            $table->enum('delivery_status', ['pending', 'assigned', 'in_transit', 'delivered', 'failed', 'cancelled'])->default('pending');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deliveries');
    }
};
