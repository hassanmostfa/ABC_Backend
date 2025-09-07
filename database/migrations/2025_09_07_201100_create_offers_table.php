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
        Schema::create('offers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('target_product_id')->constrained('products')->onDelete('cascade');
            $table->integer('target_quantity');
            $table->foreignId('gift_product_id')->constrained('products')->onDelete('cascade');
            $table->integer('gift_quantity');
            $table->datetime('offer_start_date');
            $table->datetime('offer_end_date');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('offers');
    }
};
