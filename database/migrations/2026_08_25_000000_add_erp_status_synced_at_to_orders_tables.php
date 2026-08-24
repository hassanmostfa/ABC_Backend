<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('erp_status_synced_at')->nullable()->after('erp_invoice_no');
        });

        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->timestamp('erp_status_synced_at')->nullable()->after('sent_to_erp_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('erp_status_synced_at');
        });

        Schema::table('subscription_orders', function (Blueprint $table) {
            $table->dropColumn('erp_status_synced_at');
        });
    }
};
