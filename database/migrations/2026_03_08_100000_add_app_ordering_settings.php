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
        // App ordering enabled: 1 = yes, 0 = no (when 0, show message to go to stores)
        if (DB::table('settings')->where('key', 'app_ordering_enabled')->doesntExist()) {
            DB::table('settings')->insert([
                'key' => 'app_ordering_enabled',
                'value' => '1',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // Translatable message when app ordering is disabled
        if (DB::table('settings')->where('key', 'app_ordering_disabled_message')->doesntExist()) {
            $id = DB::table('settings')->insertGetId([
                'key' => 'app_ordering_disabled_message',
                'value' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('setting_translations')->insert([
                [
                    'setting_id' => $id,
                    'locale' => 'en',
                    'value' => 'Ordering from the app is currently unavailable. Please visit our stores to place your order.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'setting_id' => $id,
                    'locale' => 'ar',
                    'value' => 'الطلب من التطبيق غير متاح حالياً. يرجى زيارة فروعنا لتقديم طلبك.',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $messageSettingId = DB::table('settings')->where('key', 'app_ordering_disabled_message')->value('id');
        if ($messageSettingId) {
            DB::table('setting_translations')->where('setting_id', $messageSettingId)->delete();
        }
        DB::table('settings')->whereIn('key', ['app_ordering_enabled', 'app_ordering_disabled_message'])->delete();
    }
};
