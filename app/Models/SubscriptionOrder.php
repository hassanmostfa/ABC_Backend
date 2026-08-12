<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SubscriptionOrder extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_subscription_id',
        'customer_id',
        'order_sequence',
        'month_number',
        'order_in_month',
        'scheduled_delivery_date',
        'status',
        'total_amount',
        'notes',
        'erp_data',
        'sent_to_erp_at',
    ];

    protected $casts = [
        'scheduled_delivery_date' => 'date',
        'total_amount' => 'decimal:3',
        'order_sequence' => 'integer',
        'month_number' => 'integer',
        'order_in_month' => 'integer',
        'erp_data' => 'array',
        'sent_to_erp_at' => 'datetime',
    ];

    /**
     * Get the customer subscription
     */
    public function customerSubscription()
    {
        return $this->belongsTo(CustomerSubscription::class);
    }

    /**
     * Get the customer
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get all items for this order
     */
    public function items()
    {
        return $this->hasMany(SubscriptionOrderItem::class);
    }

    /**
     * Generate unique order number
     */
    public static function generateOrderNumber(): string
    {
        $prefix = 'SUB';
        $timestamp = now()->format('YmdHis');
        $random = strtoupper(substr(md5(uniqid(rand(), true)), 0, 4));
        return "{$prefix}-{$timestamp}-{$random}";
    }
}
