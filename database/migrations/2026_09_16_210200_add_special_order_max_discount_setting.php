<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('settings')->where('key', 'special_order_max_discount_percentage')->doesntExist()) {
            DB::table('settings')->insert([
                'key' => 'special_order_max_discount_percentage',
                'value' => '50',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('settings')->where('key', 'special_order_max_discount_percentage')->delete();
    }
};
