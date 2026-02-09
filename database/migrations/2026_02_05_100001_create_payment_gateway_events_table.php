<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Audit log for webhook payloads (no sensitive data in application logs).
     */
    public function up(): void
    {
        Schema::create('payment_gateway_events', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 50)->index();
            $table->string('event_type', 50)->index();
            $table->string('track_id', 255)->nullable()->index();
            $table->string('receipt_id', 255)->nullable()->index();
            $table->json('payload');
            $table->timestamp('received_at');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_gateway_events');
    }
};
