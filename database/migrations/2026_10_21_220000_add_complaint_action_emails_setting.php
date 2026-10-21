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
        if (DB::table('settings')->where('key', 'complaint_action_emails')->doesntExist()) {
            DB::table('settings')->insert([
                'key' => 'complaint_action_emails',
                'value' => json_encode(config('complaints.action_emails', [])),
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
        DB::table('settings')->where('key', 'complaint_action_emails')->delete();
    }
};
