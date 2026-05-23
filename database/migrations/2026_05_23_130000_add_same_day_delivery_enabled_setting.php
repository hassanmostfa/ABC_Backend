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
        if (DB::table('settings')->where('key', 'same_day_delivery_enabled')->doesntExist()) {
            DB::table('settings')->insert([
                'key' => 'same_day_delivery_enabled',
                'value' => '1',
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
        DB::table('settings')->where('key', 'same_day_delivery_enabled')->delete();
    }
};
