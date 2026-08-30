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
        Schema::table('product_variants', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')
                ->default(0)
                ->after('is_active')
                ->comment('Display order within the product subcategory');
        });

        $variants = DB::table('product_variants')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->orderBy('products.subcategory_id')
            ->orderBy('product_variants.id')
            ->select('product_variants.id', 'products.subcategory_id')
            ->get();

        $counters = [];
        foreach ($variants as $variant) {
            $key = (string) ($variant->subcategory_id ?? 'none');
            $counters[$key] = ($counters[$key] ?? 0) + 1;

            DB::table('product_variants')
                ->where('id', $variant->id)
                ->update(['sort_order' => $counters[$key]]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('sort_order');
        });
    }
};
