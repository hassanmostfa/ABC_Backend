<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->unsignedBigInteger('creator_id')->nullable()->after('customer_id');
            $table->string('creator_type')->nullable()->after('creator_id');

            $table->index(['creator_id', 'creator_type']);
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropIndex(['creator_id', 'creator_type']);
            $table->dropColumn(['creator_id', 'creator_type']);
        });
    }
};
