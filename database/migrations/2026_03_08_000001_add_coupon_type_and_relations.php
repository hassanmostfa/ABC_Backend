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
        Schema::table('coupons', function (Blueprint $table) {
            $table->string('type', 32)->default('general')->after('code'); // general, product_variant, welcome
            $table->foreignId('customer_id')->nullable()->after('is_active')->constrained('customers')->nullOnDelete();
        });

        Schema::create('coupon_product_variant', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coupon_id')->constrained()->onDelete('cascade');
            $table->foreignId('product_variant_id')->constrained()->onDelete('cascade');
            $table->unique(['coupon_id', 'product_variant_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coupon_product_variant');

        Schema::table('coupons', function (Blueprint $table) {
            $table->dropForeign(['customer_id']);
            $table->dropColumn(['type', 'customer_id']);
        });
    }
};
