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
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->decimal('lat', 10, 8)->nullable()->after('area_id');
            $table->decimal('lng', 11, 8)->nullable()->after('lat');
            $table->string('type', 20)->default('house')->after('lng'); // apartment, house, office
            $table->string('building_name')->nullable()->after('type');
            $table->string('apartment_number')->nullable()->after('building_name');
            $table->string('company')->nullable()->after('apartment_number');
            $table->string('phone_number')->nullable()->after('company');
            $table->text('additional_directions')->nullable()->after('phone_number');
            $table->string('address_label')->nullable()->after('additional_directions');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('customer_addresses', function (Blueprint $table) {
            $table->dropColumn([
                'lat', 'lng', 'type', 'building_name', 'apartment_number',
                'company', 'phone_number', 'additional_directions', 'address_label'
            ]);
        });
    }
};
