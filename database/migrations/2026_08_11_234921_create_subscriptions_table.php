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
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('offer_id')->constrained('offers')->onDelete('cascade');
            $table->enum('period', ['3', '6', '12'])->comment('Subscription period in months');
            $table->integer('points')->default(0)->comment('Points earned from this subscription');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            
            // Ensure unique combination of offer_id and period
            $table->unique(['offer_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};
