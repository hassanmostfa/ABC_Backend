<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // products / variants / order items / offer rewards
        DB::statement("ALTER TABLE product_variants MODIFY COLUMN price DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE order_items MODIFY COLUMN unit_price DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE order_items MODIFY COLUMN total_price DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE offer_rewards MODIFY COLUMN discount_amount DECIMAL(10,3) NULL");

        // orders / payments / refunds / points / wallets
        DB::statement("ALTER TABLE orders MODIFY COLUMN total_amount DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE payments MODIFY COLUMN amount DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE payments MODIFY COLUMN bonus_amount DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE payments MODIFY COLUMN total_amount DECIMAL(10,3) NULL");
        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN amount DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE points_transactions MODIFY COLUMN amount DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE wallets MODIFY COLUMN balance DECIMAL(10,3) NOT NULL DEFAULT 0.000");

        // invoices
        DB::statement("ALTER TABLE invoices MODIFY COLUMN amount_due DECIMAL(10,3) NOT NULL");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN tax_amount DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN offer_discount DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN points_discount DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN total_discount DECIMAL(10,3) NOT NULL DEFAULT 0.000");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN delivery_fee DECIMAL(10,3) NOT NULL DEFAULT 0.000");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE products MODIFY COLUMN price DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE product_variants MODIFY COLUMN price DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE order_items MODIFY COLUMN unit_price DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE order_items MODIFY COLUMN total_price DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE offer_rewards MODIFY COLUMN discount_amount DECIMAL(10,2) NULL");

        DB::statement("ALTER TABLE orders MODIFY COLUMN total_amount DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE payments MODIFY COLUMN amount DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE payments MODIFY COLUMN bonus_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE payments MODIFY COLUMN total_amount DECIMAL(10,2) NULL");
        DB::statement("ALTER TABLE refund_requests MODIFY COLUMN amount DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE points_transactions MODIFY COLUMN amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE wallets MODIFY COLUMN balance DECIMAL(10,2) NOT NULL DEFAULT 0.00");

        DB::statement("ALTER TABLE invoices MODIFY COLUMN amount_due DECIMAL(10,2) NOT NULL");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN tax_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN offer_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN points_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN total_discount DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        DB::statement("ALTER TABLE invoices MODIFY COLUMN delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0.00");
    }
};
