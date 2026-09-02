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
        $now = now();
        $keys = [
            'android_show_update_dialog' => '0',
            'android_force_update' => '0',
            'ios_show_update_dialog' => '0',
            'ios_force_update' => '0',
        ];

        foreach ($keys as $key => $value) {
            if (DB::table('settings')->where('key', $key)->doesntExist()) {
                DB::table('settings')->insert([
                    'key' => $key,
                    'value' => $value,
                    'created_at' => $now,
                    'updated_at' => $now,
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
            'android_show_update_dialog',
            'android_force_update',
            'ios_show_update_dialog',
            'ios_force_update',
        ])->delete();
    }
};
