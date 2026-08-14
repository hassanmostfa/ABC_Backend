<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->string('checkout_number', 50)->unique();
            $table->json('payload');
            $table->string('payment_gateway_src', 20)->nullable()->comment('knet, cc');
            $table->decimal('amount_due', 10, 3);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->string('ottu_session_id', 128)->nullable()->index();
            $table->text('payment_link')->nullable();
            $table->foreignId('customer_subscription_id')->nullable()->constrained('customer_subscriptions')->nullOnDelete();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('subscription_checkout_id')
                ->nullable()
                ->after('order_checkout_id')
                ->constrained('subscription_checkouts')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_checkout_id');
        });

        Schema::dropIfExists('subscription_checkouts');
    }
};
