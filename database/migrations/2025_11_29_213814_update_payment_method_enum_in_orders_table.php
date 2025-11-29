<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modify the enum to include 'online_link'
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash', 'wallet', 'online_link') NULL");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert back to original enum values
        DB::statement("ALTER TABLE orders MODIFY COLUMN payment_method ENUM('cash', 'wallet') NULL");
    }
};
