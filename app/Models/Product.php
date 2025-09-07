<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Product extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'image',
        'name_en',
        'name_ar',
        'size',
        'quantity',
        'price',
        'category_id',
        'subcategory_id',
        'description_en',
        'description_ar',
        'sku',
        'short_item',
        'has_variants',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'price' => 'decimal:2',
        'quantity' => 'integer',
        'has_variants' => 'boolean',
        'is_active' => 'boolean',
    ];

    /**
     * Get the category that owns the product
     */
    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * Get the subcategory that owns the product
     */
    public function subcategory()
    {
        return $this->belongsTo(Subcategory::class);
    }

    /**
     * Get the variants for the product
     */
    public function variants()
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Get active variants for the product
     */
    public function activeVariants()
    {
        return $this->hasMany(ProductVariant::class)->where('is_active', true);
    }

    /**
     * Get offers where this product is the target product
     */
    public function targetOffers()
    {
        return $this->hasMany(Offer::class, 'target_product_id');
    }

    /**
     * Get offers where this product is the gift product
     */
    public function giftOffers()
    {
        return $this->hasMany(Offer::class, 'gift_product_id');
    }

    /**
     * Get all offers related to this product (both target and gift)
     */
    public function offers()
    {
        return $this->targetOffers()->union($this->giftOffers());
    }
}
