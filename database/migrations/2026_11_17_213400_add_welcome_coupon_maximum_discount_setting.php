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
        if (DB::table('settings')->where('key', 'welcome_coupon_maximum_discount_amount')->doesntExist()) {
            DB::table('settings')->insert([
                'key' => 'welcome_coupon_maximum_discount_amount',
                'value' => '',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->where('key', 'welcome_coupon_maximum_discount_amount')->delete();
    }
};
