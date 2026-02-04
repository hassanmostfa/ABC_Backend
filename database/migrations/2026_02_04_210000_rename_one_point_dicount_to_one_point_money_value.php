<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Rename setting key from one_point_dicount to one_point_money_value (money value per 1 point).
     */
    public function up(): void
    {
        DB::table('settings')
            ->where('key', 'one_point_dicount')
            ->update(['key' => 'one_point_money_value']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('settings')
            ->where('key', 'one_point_money_value')
            ->update(['key' => 'one_point_dicount']);
    }
};
