<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            $foreignColumns = ['target_product_id', 'gift_product_id'];

            foreach ($foreignColumns as $column) {
                $hasForeignKey = DB::table('information_schema.KEY_COLUMN_USAGE')
                    ->whereRaw('TABLE_SCHEMA = DATABASE()')
                    ->where('TABLE_NAME', 'offers')
                    ->where('COLUMN_NAME', $column)
                    ->whereNotNull('REFERENCED_TABLE_NAME')
                    ->exists();

                if ($hasForeignKey) {
                    $table->dropForeign([$column]);
                }
            }

            $columnsToDrop = [
                'target_product_id',
                'target_quantity',
                'gift_product_id',
                'gift_quantity',
                'has_gift_variants',
                'has_target_variants',
            ];

            $existingColumns = array_values(array_filter(
                $columnsToDrop,
                fn (string $column): bool => Schema::hasColumn('offers', $column)
            ));

            if ($existingColumns !== []) {
                $table->dropColumn($existingColumns);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('offers', function (Blueprint $table) {
            // Add back the columns
            $table->foreignId('target_product_id')->constrained('products')->onDelete('cascade');
            $table->integer('target_quantity');
            $table->foreignId('gift_product_id')->constrained('products')->onDelete('cascade');
            $table->integer('gift_quantity');
            $table->boolean('has_gift_variants')->default(false);
            $table->boolean('has_target_variants')->default(false);
        });
    }
};
