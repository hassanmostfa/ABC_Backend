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
            // Check if charity_id column doesn't exist before adding it
            if (!Schema::hasColumn('offers', 'charity_id')) {
                $table->foreignId('charity_id')->nullable()->constrained()->onDelete('cascade');
                $table->index(['charity_id']);
            }
            
            // Add reward_type column
            $table->enum('reward_type', ['products', 'discount'])->default('products');
            $table->index(['reward_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            if (Schema::hasColumn('offers', 'charity_id')) {
                $table->dropForeign(['charity_id']);
                $table->dropIndex(['charity_id']);
                $table->dropColumn('charity_id');
            }
            
            $table->dropIndex(['reward_type']);
            $table->dropColumn('reward_type');
        });
    }
};
