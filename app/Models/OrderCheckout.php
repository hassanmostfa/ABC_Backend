<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderCheckout extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'customer_id',
        'source',
        'order_number',
        'payload',
        'payment_gateway_src',
        'amount_due',
        'status',
        'ottu_session_id',
        'payment_link',
        'order_id',
        'expires_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'amount_due' => 'decimal:2',
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isBlockingNewCheckout(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING], true);
    }

    public function draft(): array
    {
        return is_array($this->payload) ? $this->payload : [];
    }
}
