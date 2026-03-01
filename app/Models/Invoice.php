<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'invoice_number',
        'amount_due',
        'tax_amount',
        'delivery_fee',
        'offer_discount',
        'coupons_discount',
        'used_points',
        'points_discount',
        'total_discount',
        'payment_link',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'amount_due' => 'decimal:3',
        'tax_amount' => 'decimal:3',
        'delivery_fee' => 'decimal:3',
        'offer_discount' => 'decimal:3',
        'coupons_discount' => 'decimal:3',
        'used_points' => 'integer',
        'points_discount' => 'decimal:3',
        'total_discount' => 'decimal:3',
        'paid_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }
}
