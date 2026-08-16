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
        if (DB::table('settings')->where('key', 'referral_points')->doesntExist()) {
            DB::table('settings')->insert([
                'key' => 'referral_points',
                'value' => '10',
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
        DB::table('settings')->where('key', 'referral_points')->delete();
    }
};
