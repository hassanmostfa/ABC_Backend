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
        $keys = [
            ['key' => 'welcome_coupon_discount_type', 'value' => 'percentage'],
            ['key' => 'welcome_coupon_discount_value', 'value' => '10'],
            ['key' => 'welcome_coupon_minimum_order_amount', 'value' => '0'],
        ];

        foreach ($keys as $row) {
            if (DB::table('settings')->where('key', $row['key'])->doesntExist()) {
                DB::table('settings')->insert([
                    'key' => $row['key'],
                    'value' => $row['value'],
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')->whereIn('key', [
            'welcome_coupon_discount_type',
            'welcome_coupon_discount_value',
            'welcome_coupon_minimum_order_amount',
        ])->delete();
    }
};
