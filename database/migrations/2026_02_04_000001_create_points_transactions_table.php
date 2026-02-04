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
        Schema::create('points_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->onDelete('cascade');
            $table->enum('type', ['points_to_wallet', 'points_earned']);
            $table->decimal('amount', 10, 2)->default(0.00)->comment('Money value from one_point_money_value setting (for points_to_wallet)');
            $table->integer('points')->default(0)->comment('Points involved in the transaction');
            $table->string('description')->nullable();
            $table->string('reference_type')->nullable()->comment('e.g. Order, Payment');
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['customer_id', 'type']);
            $table->index(['customer_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('points_transactions');
    }
};
