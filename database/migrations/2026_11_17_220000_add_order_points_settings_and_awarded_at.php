<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('order_points_awarded_at')->nullable()->after('status');
        });

        $settings = [
            ['key' => 'order_points_enabled', 'value' => '1'],
            ['key' => 'order_points_per_kwd', 'value' => '10'],
        ];

        foreach ($settings as $row) {
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
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('order_points_awarded_at');
        });

        DB::table('settings')->whereIn('key', [
            'order_points_enabled',
            'order_points_per_kwd',
        ])->delete();
    }
};
