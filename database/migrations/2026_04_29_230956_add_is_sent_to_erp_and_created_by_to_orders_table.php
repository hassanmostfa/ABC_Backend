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
            $table->boolean('is_sent_to_erp')->default(false)->after('payment_gateway_src');
            $table->unsignedBigInteger('created_by_id')->nullable()->after('is_sent_to_erp');
            $table->string('created_by_type')->nullable()->after('created_by_id');
            
            $table->index(['created_by_id', 'created_by_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['created_by_id', 'created_by_type']);
            $table->dropColumn(['is_sent_to_erp', 'created_by_id', 'created_by_type']);
        });
    }
};
