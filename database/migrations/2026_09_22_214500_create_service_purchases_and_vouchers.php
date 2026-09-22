<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->string('checkout_number', 50)->unique();
            $table->string('service_name');
            $table->string('service_provider_email');
            $table->string('payment_gateway_src', 20)->nullable();
            $table->decimal('amount_due', 12, 3);
            $table->enum('status', ['pending', 'paid', 'failed', 'expired', 'cancelled'])->default('pending');
            $table->string('ottu_session_id', 128)->nullable()->index();
            $table->text('payment_link')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['customer_id', 'status']);
        });

        Schema::create('service_vouchers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->cascadeOnDelete();
            $table->foreignId('service_id')->nullable()->constrained('services')->nullOnDelete();
            $table->foreignId('service_checkout_id')->nullable()->constrained('service_checkouts')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->string('service_name');
            $table->string('code', 40)->unique();
            $table->decimal('amount', 12, 3);
            $table->string('payment_method', 20);
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->index(['customer_id', 'created_at']);
        });

        Schema::table('service_checkouts', function (Blueprint $table) {
            $table->foreignId('service_voucher_id')
                ->nullable()
                ->after('payment_link')
                ->constrained('service_vouchers')
                ->nullOnDelete();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('service_checkout_id')
                ->nullable()
                ->after('subscription_checkout_id')
                ->constrained('service_checkouts')
                ->nullOnDelete();
        });

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('order', 'wallet_charge', 'order_checkout', 'subscription', 'service') NOT NULL DEFAULT 'order'");
        }
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_checkout_id');
        });

        Schema::table('service_checkouts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('service_voucher_id');
        });

        Schema::dropIfExists('service_vouchers');
        Schema::dropIfExists('service_checkouts');

        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement("UPDATE payments SET type = 'order' WHERE type = 'service'");
            DB::statement("ALTER TABLE payments MODIFY COLUMN type ENUM('order', 'wallet_charge', 'order_checkout', 'subscription') NOT NULL DEFAULT 'order'");
        }
    }
};
