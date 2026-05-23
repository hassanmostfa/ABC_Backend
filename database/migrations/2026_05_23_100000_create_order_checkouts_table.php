<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('order_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('source', 20)->default('call_center')->comment('app, web, call_center');
            $table->string('order_number', 50)->unique();
            $table->json('payload');
            $table->string('payment_gateway_src', 20)->nullable()->comment('knet, cc');
            $table->decimal('amount_due', 10, 2);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->string('ottu_session_id', 128)->nullable()->index();
            $table->text('payment_link')->nullable();
            $table->foreignId('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_checkouts');
    }
};
