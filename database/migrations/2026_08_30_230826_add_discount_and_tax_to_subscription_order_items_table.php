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
        Schema::table('subscription_order_items', function (Blueprint $table) {
            $table->decimal('discount', 10, 3)
                ->default(0)
                ->after('total_price')
                ->comment('Discount amount applied to this item');
            
            $table->decimal('tax', 10, 3)
                ->default(0)
                ->after('discount')
                ->comment('Tax amount for this item');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('subscription_order_items', function (Blueprint $table) {
            $table->dropColumn(['discount', 'tax']);
        });
    }
};
