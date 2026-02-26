<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price', 10, 3)->default(0.000)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 3)->change();
            $table->decimal('total_price', 10, 3)->change();
        });

        Schema::table('offer_rewards', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 3)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->decimal('price', 10, 2)->default(0.00)->change();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('unit_price', 10, 2)->change();
            $table->decimal('total_price', 10, 2)->change();
        });

        Schema::table('offer_rewards', function (Blueprint $table) {
            $table->decimal('discount_amount', 10, 2)->nullable()->change();
        });
    }
};
