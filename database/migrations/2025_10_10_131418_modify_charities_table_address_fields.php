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
        Schema::table('charities', function (Blueprint $table) {
            // Drop the existing address columns
            $table->dropColumn(['address']);
            
            // Add foreign key columns for location
            $table->foreignId('country_id')->nullable()->constrained('countries')->onDelete('set null');
            $table->foreignId('governorate_id')->nullable()->constrained('governorates')->onDelete('set null');
            $table->foreignId('area_id')->nullable()->constrained('areas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('charities', function (Blueprint $table) {
            // Drop foreign key columns
            $table->dropForeign(['country_id']);
            $table->dropForeign(['governorate_id']);
            $table->dropForeign(['area_id']);
            $table->dropColumn(['country_id', 'governorate_id', 'area_id']);
            
            // Restore the original address column
            $table->text('address')->nullable();
        });
    }
};
