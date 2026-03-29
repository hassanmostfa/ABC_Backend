<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_id',
        'variant_id',
        'name',
        'sku',
        'quantity',
        'unit_price',
        'total_price',
        'tax',
        'discount',
        'is_offer',
        'offer_line_kind',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:3',
        'total_price' => 'decimal:3',
        'tax' => 'decimal:3',
        'discount' => 'decimal:3',
        'is_offer' => 'boolean',
    ];

    /**
     * Tax on net line (total_price - discount) using settings tax rate (e.g. 0.15).
     */
    public static function computeLineTax(float $totalPrice, float $discount, float $taxRate): float
    {
        $net = max(0, $totalPrice - $discount);

        return round($net * $taxRate, 3);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function variant()
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }
}
