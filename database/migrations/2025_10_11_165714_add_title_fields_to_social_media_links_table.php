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
        Schema::table('social_media_links', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('icon');
            $table->string('title_ar')->nullable()->after('title_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('social_media_links', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'title_ar']);
        });
    }
};
