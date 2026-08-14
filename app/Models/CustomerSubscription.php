<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CustomerSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'subscription_id',
        'orders_per_month',
        'start_date',
        'end_date',
        'status',
        'total_amount',
        'total_orders',
        'metadata',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'total_amount' => 'decimal:3',
        'total_orders' => 'integer',
        'orders_per_month' => 'integer',
        'metadata' => 'array',
    ];

    /**
     * Get the customer that owns the subscription
     */
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * Get the subscription (offer + period + points)
     */
    public function subscription()
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * Get all orders for this subscription
     */
    public function orders()
    {
        return $this->hasMany(SubscriptionOrder::class);
    }

    /**
     * Get the purchase invoice for this subscription
     */
    public function invoice()
    {
        return $this->hasOne(Invoice::class);
    }

    /**
     * Get refund requests for this customer subscription
     */
    public function refundRequests()
    {
        return $this->hasMany(RefundRequest::class);
    }

    /**
     * Scope to get active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Check if subscription is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active' && 
               now()->between($this->start_date, $this->end_date);
    }
}
