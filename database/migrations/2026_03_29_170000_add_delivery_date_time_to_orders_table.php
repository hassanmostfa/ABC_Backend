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
        Schema::table('orders', function (Blueprint $table) {
            $table->date('delivery_date')->nullable()->after('delivery_type');
            $table->time('delivery_time')->nullable()->after('delivery_date');
            $table->index('delivery_date');
            $table->index('delivery_time');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['delivery_date']);
            $table->dropIndex(['delivery_time']);
            $table->dropColumn(['delivery_date', 'delivery_time']);
        });
    }
};
