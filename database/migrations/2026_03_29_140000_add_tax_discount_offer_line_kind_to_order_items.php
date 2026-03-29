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
        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('tax', 10, 3)->default(0)->after('total_price');
            $table->decimal('discount', 10, 3)->default(0)->after('tax');
            $table->string('offer_line_kind', 20)->nullable()->after('is_offer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropColumn(['tax', 'discount', 'offer_line_kind']);
        });
    }
};
