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
        Schema::table('notifications', function (Blueprint $table) {
            $table->dropColumn(['title', 'message', 'title_en', 'title_ar', 'message_en', 'message_ar']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            $table->string('title')->nullable();
            $table->text('message')->nullable();
            $table->string('title_en')->nullable();
            $table->string('title_ar')->nullable();
            $table->text('message_en')->nullable();
            $table->text('message_ar')->nullable();
        });
    }
};
