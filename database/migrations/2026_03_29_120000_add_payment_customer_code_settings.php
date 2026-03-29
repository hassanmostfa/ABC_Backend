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
            ['key' => 'cash_customer_code', 'value' => ''],
            ['key' => 'wallet_customer_code', 'value' => ''],
            ['key' => 'knet_customer_code', 'value' => ''],
            ['key' => 'credit_card_customer_code', 'value' => ''],
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
            'cash_customer_code',
            'wallet_customer_code',
            'knet_customer_code',
            'credit_card_customer_code',
        ])->delete();
    }
};
