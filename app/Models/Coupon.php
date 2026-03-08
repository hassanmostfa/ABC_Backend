<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    use HasFactory;

    public const TYPE_GENERAL = 'general';
    public const TYPE_PRODUCT_VARIANT = 'product_variant';
    public const TYPE_WELCOME = 'welcome';

    protected $fillable = [
        'code',
        'type',
        'name',
        'discount_type',
        'discount_value',
        'minimum_order_amount',
        'maximum_discount_amount',
        'usage_limit',
        'used_count',
        'starts_at',
        'expires_at',
        'is_active',
        'customer_id',
    ];

    protected $casts = [
        'discount_value' => 'decimal:3',
        'minimum_order_amount' => 'decimal:3',
        'maximum_discount_amount' => 'decimal:3',
        'is_active' => 'boolean',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    public function scopeWelcomeTemplate($query)
    {
        return $query->where('type', self::TYPE_WELCOME)->whereNull('customer_id');
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function productVariants()
    {
        return $this->belongsToMany(ProductVariant::class, 'coupon_product_variant');
    }
}
