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
        Schema::table('offers', function (Blueprint $table) {
            $table->string('title_en')->nullable()->after('id');
            $table->string('title_ar')->nullable()->after('title_en');
            $table->text('description_en')->nullable()->after('title_ar');
            $table->text('description_ar')->nullable()->after('description_en');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $table->dropColumn(['title_en', 'title_ar', 'description_en', 'description_ar']);
        });
    }
};
