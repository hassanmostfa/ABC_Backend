<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Payment link is needed for wallet charge payments (stored on payment when no invoice).
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->text('payment_link')->nullable()->after('total_amount');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('payment_link');
        });
    }
};
