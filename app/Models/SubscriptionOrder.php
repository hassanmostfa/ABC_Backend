<?php

namespace App\Models;

use App\Support\SubscriptionPricing;
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
        'erp_status_synced_at',
    ];

    protected $casts = [
        'scheduled_delivery_date' => 'date',
        'total_amount' => 'decimal:3',
        'order_sequence' => 'integer',
        'month_number' => 'integer',
        'order_in_month' => 'integer',
        'erp_data' => 'array',
        'sent_to_erp_at' => 'datetime',
        'erp_status_synced_at' => 'datetime',
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
     * Resolve the payable total for this order.
     * Falls back to item-level reconstruction when legacy rows have stale totals.
     */
    public function resolvedTotalAmount(): float
    {
        $storedTotal = round((float) $this->total_amount, 3);
        $calculatedTotal = $this->calculateTotalFromItems();

        return $calculatedTotal ?? $storedTotal;
    }

    private function calculateTotalFromItems(): ?float
    {
        $this->loadMissing('items');

        if ($this->items->isEmpty()) {
            return null;
        }

        $grossTotal = 0.0;
        $conditionTotal = 0.0;
        $itemDiscountTotal = 0.0;

        foreach ($this->items as $item) {
            $lineGross = (float) $item->unit_price * (int) $item->quantity;
            $grossTotal += $lineGross;
            $itemDiscountTotal += (float) ($item->discount ?? 0);

            if ($item->type === 'condition') {
                $conditionTotal += $lineGross;
            }
        }

        if ($itemDiscountTotal > 0) {
            return round(max(0, $grossTotal - $itemDiscountTotal), 3);
        }

        $this->loadMissing('customerSubscription.subscription.offer');
        $offer = $this->customerSubscription?->subscription?->offer;

        if (!$offer) {
            return round($grossTotal, 3);
        }

        if ($offer->reward_type === 'products') {
            return round($conditionTotal > 0 ? $conditionTotal : $grossTotal, 3);
        }

        if ($offer->reward_type === 'discount') {
            $monthly = SubscriptionPricing::monthlyPrices($offer);
            $before = (float) ($monthly['before'] ?? 0);
            $after = (float) ($monthly['after'] ?? 0);

            if ($before > 0) {
                $basis = $conditionTotal > 0 ? $conditionTotal : $grossTotal;
                return round(max(0, $basis * ($after / $before)), 3);
            }
        }

        return round($grossTotal, 3);
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
