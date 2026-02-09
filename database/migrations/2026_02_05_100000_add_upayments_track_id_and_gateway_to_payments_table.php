<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Add gateway identifiers for idempotency and getpaymentstatus verification.
     */
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('gateway', 50)->default('upayments')->after('payment_number');
            $table->string('track_id', 255)->nullable()->after('gateway');
            $table->string('tran_id', 255)->nullable()->after('track_id')->comment('Gateway transaction id');
            $table->string('payment_id', 255)->nullable()->after('tran_id')->comment('Gateway payment id');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unique(['gateway', 'track_id'], 'payments_gateway_track_id_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropUnique('payments_gateway_track_id_unique');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['gateway', 'track_id', 'tran_id', 'payment_id']);
        });
    }
};
