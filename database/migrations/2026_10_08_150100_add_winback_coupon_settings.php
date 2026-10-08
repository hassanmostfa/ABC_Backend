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
            ['key' => 'winback_coupon_enabled', 'value' => '1'],
            ['key' => 'winback_coupon_discount_value', 'value' => '10'],
            ['key' => 'winback_coupon_valid_days', 'value' => '30'],
            ['key' => 'winback_inactive_days', 'value' => '30'],
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
            'winback_coupon_enabled',
            'winback_coupon_discount_value',
            'winback_coupon_valid_days',
            'winback_inactive_days',
        ])->delete();
    }
};
