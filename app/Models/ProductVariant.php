<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProductVariant extends Model
{
    use HasFactory;

    static string $STORAGE_DIR = "images/products/variants";

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'product_id',
        'size',
        'short_item',
        'sku',
        'quantity',
        'price',
        'image',
        'is_active',
        'sort_order',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:3',
        'quantity' => 'integer',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Get the product that owns the variant
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the price for this variant
     */
    public function getTotalPriceAttribute()
    {
        return $this->price;
    }

    /**
     * Next sort_order for a variant in the same subcategory (or product).
     */
    public static function nextSortOrderForProduct(?int $productId): int
    {
        $query = static::query();

        if ($productId) {
            $subcategoryId = Product::query()->whereKey($productId)->value('subcategory_id');

            if ($subcategoryId) {
                $query->whereHas('product', function ($productQuery) use ($subcategoryId) {
                    $productQuery->where('subcategory_id', $subcategoryId);
                });
            } else {
                $query->where('product_id', $productId);
            }
        }

        return ((int) $query->max('sort_order')) + 1;
    }
}
