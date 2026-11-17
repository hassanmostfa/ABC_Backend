<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->timestamp('referral_rewarded_at')->nullable()->after('referred_by');
        });

        // Referrals that already received points at signup must not be paid again.
        DB::table('customers')
            ->whereNotNull('referred_by')
            ->whereNull('referral_rewarded_at')
            ->update(['referral_rewarded_at' => now()]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('referral_rewarded_at');
        });
    }
};
