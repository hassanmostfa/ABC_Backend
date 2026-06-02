<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('admins', 'admin_id')) {
            Schema::table('admins', function (Blueprint $table) {
                $table->string('admin_id', 50)->nullable()->unique()->after('id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('admins', function (Blueprint $table) {
            $table->dropUnique(['admin_id']);
            $table->dropColumn('admin_id');
        });
    }
};
