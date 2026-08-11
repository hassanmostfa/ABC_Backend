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
        $key = 'subscription_terms_and_conditions';
        $exists = DB::table('settings')->where('key', $key)->exists();
        
        if (!$exists) {
            DB::table('settings')->insert([
                'key' => $key,
                'value' => null,
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
        DB::table('settings')->where('key', 'subscription_terms_and_conditions')->delete();
    }
};
