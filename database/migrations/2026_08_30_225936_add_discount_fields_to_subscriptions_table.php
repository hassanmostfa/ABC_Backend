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
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->enum('discount_type', ['none', 'percentage', 'fixed', 'free_months'])
                ->default('none')
                ->after('points')
                ->comment('Type of discount: none, percentage, fixed, or free_months');
            
            $table->decimal('discount_value', 10, 3)
                ->nullable()
                ->after('discount_type')
                ->comment('Discount value (percentage 0-100 or fixed amount)');
            
            $table->integer('discount_free_months')
                ->nullable()
                ->after('discount_value')
                ->comment('Number of free months when discount_type is free_months');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn(['discount_type', 'discount_value', 'discount_free_months']);
        });
    }
};
