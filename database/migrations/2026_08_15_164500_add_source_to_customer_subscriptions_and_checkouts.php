<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->string('source', 20)->default('app')->after('total_orders')->comment('app, web');
            $table->index('source');
        });

        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->string('source', 20)->default('app')->after('payment_gateway_src')->comment('app, web');
        });
    }

    public function down(): void
    {
        Schema::table('customer_subscriptions', function (Blueprint $table) {
            $table->dropIndex(['source']);
            $table->dropColumn('source');
        });

        Schema::table('subscription_checkouts', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
