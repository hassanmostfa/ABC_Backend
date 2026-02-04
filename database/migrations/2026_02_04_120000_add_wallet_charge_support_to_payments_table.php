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
            $table->dropForeign(['invoice_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable()->change();
            $table->foreignId('customer_id')->nullable()->after('invoice_id')->constrained('customers')->onDelete('cascade');
            $table->string('reference', 50)->nullable()->unique()->after('customer_id')->comment('WCH-2026-000001 for wallet charges');
            $table->enum('type', ['order', 'wallet_charge'])->default('order')->after('reference');
            $table->decimal('bonus_amount', 10, 2)->default(0)->after('amount')->comment('Gift amount for wallet charge');
            $table->decimal('total_amount', 10, 2)->nullable()->after('bonus_amount')->comment('Amount+bonus for wallet charge');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['customer_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('invoice_id')->nullable(false)->change();
            $table->dropColumn(['customer_id', 'reference', 'type', 'bonus_amount', 'total_amount']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('invoice_id')->references('id')->on('invoices')->onDelete('cascade');
        });
    }
};
