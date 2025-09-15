<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class OfferCondition extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offer_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'quantity' => 'integer',
        'is_active' => 'boolean',
    ];

    /**
     * Get the offer that owns the condition
     */
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Get the product for this condition
     */
    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the product variant for this condition (if applicable)
     */
    public function productVariant()
    {
        return $this->belongsTo(ProductVariant::class);
    }
}
