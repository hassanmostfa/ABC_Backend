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
        Schema::table('offer_rewards', function (Blueprint $table) {
            // Drop the existing foreign key constraint
            $table->dropForeign(['product_id']);
            
            // Make product_id nullable
            $table->foreignId('product_id')->nullable()->change();
            
            // Re-add the foreign key constraint with nullable
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offer_rewards', function (Blueprint $table) {
            // Drop the foreign key constraint
            $table->dropForeign(['product_id']);
            
            // Make product_id not nullable again
            $table->foreignId('product_id')->nullable(false)->change();
            
            // Re-add the foreign key constraint
            $table->foreign('product_id')->references('id')->on('products')->onDelete('cascade');
        });
    }
};
