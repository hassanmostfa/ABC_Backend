<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SpecialOrder extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'order_number',
        'customer_id',
        'source',
        'payment_method',
        'payment_gateway_src',
        'payload',
        'original_amount_due',
        'final_price',
        'special_discount',
        'discount_percentage',
        'status',
        'requested_by_id',
        'reviewed_by_id',
        'reviewed_at',
        'review_notes',
        'rejection_reason',
        'order_id',
        'order_checkout_id',
    ];

    protected $casts = [
        'payload' => 'array',
        'original_amount_due' => 'decimal:3',
        'final_price' => 'decimal:3',
        'special_discount' => 'decimal:3',
        'discount_percentage' => 'decimal:2',
        'reviewed_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'requested_by_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function orderCheckout(): BelongsTo
    {
        return $this->belongsTo(OrderCheckout::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function draft(): array
    {
        return is_array($this->payload) ? $this->payload : [];
    }
}
