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
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_id')->nullable()->after('payment_number');
            $table->string('transaction_id')->nullable()->after('payment_id');
            $table->string('receipt_id')->nullable()->after('transaction_id');
            $table->text('payment_link')->nullable()->after('receipt_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['payment_id', 'transaction_id', 'receipt_id', 'payment_link']);
        });
    }
};
