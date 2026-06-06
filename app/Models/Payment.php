<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Payment extends Model
{
    use HasFactory;

    const TYPE_ORDER = 'order';
    const TYPE_WALLET_CHARGE = 'wallet_charge';
    const TYPE_ORDER_CHECKOUT = 'order_checkout';

    const STATUS_PENDING = 'pending';
    const STATUS_COMPLETED = 'completed';
    const STATUS_FAILED = 'failed';
    const STATUS_REFUNDED = 'refunded';
    const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'invoice_id',
        'customer_id',
        'creator_id',
        'creator_type',
        'order_checkout_id',
        'reference',
        'type',
        'payment_number',
        'gateway',
        'payment_gateway_src',
        'track_id',
        'tran_id',
        'payment_id',
        'receipt_id',
        'amount',
        'bonus_amount',
        'total_amount',
        'method',
        'status',
        'payment_link',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function creator(): MorphTo
    {
        return $this->morphTo();
    }

    public function orderCheckout()
    {
        return $this->belongsTo(OrderCheckout::class);
    }

    public function scopeWalletCharge($query)
    {
        return $query->where('type', self::TYPE_WALLET_CHARGE);
    }
}
