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
        Schema::table('offers', function (Blueprint $table) {
            // Drop foreign key constraints first
            $table->dropForeign(['target_product_id']);
            $table->dropForeign(['gift_product_id']);
            
            // Drop the unused columns
            $table->dropColumn([
                'target_product_id',
                'target_quantity',
                'gift_product_id',
                'gift_quantity',
                'has_gift_variants',
                'has_target_variants',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Add back the columns
            $table->foreignId('target_product_id')->constrained('products')->onDelete('cascade');
            $table->integer('target_quantity');
            $table->foreignId('gift_product_id')->constrained('products')->onDelete('cascade');
            $table->integer('gift_quantity');
            $table->boolean('has_gift_variants')->default(false);
            $table->boolean('has_target_variants')->default(false);
        });
    }
};
