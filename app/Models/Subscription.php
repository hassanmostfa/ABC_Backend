<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Subscription extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'offer_id',
        'period',
        'points',
        'is_active',
        'discount_type',
        'discount_value',
        'discount_free_months',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'points' => 'integer',
        'is_active' => 'boolean',
        'discount_value' => 'decimal:3',
        'discount_free_months' => 'integer',
    ];

    /**
     * Available subscription periods in months
     */
    const PERIODS = ['3', '6', '12'];

    /**
     * Available discount types
     */
    const DISCOUNT_TYPES = ['none', 'percentage', 'fixed', 'free_months'];

    /**
     * Get the offer that owns the subscription.
     */
    public function offer()
    {
        return $this->belongsTo(Offer::class);
    }

    /**
     * Scope to get only active subscriptions
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Get period in months as integer
     */
    public function getPeriodInMonthsAttribute(): int
    {
        return (int) $this->period;
    }

    /**
     * Check if subscription has a discount
     */
    public function hasDiscount(): bool
    {
        return $this->discount_type !== 'none' && $this->discount_type !== null;
    }

    /**
     * Calculate discount amount for a given subtotal
     */
    public function calculateDiscountAmount(float $subtotal): float
    {
        if (!$this->hasDiscount()) {
            return 0;
        }

        switch ($this->discount_type) {
            case 'percentage':
                if ($this->discount_value > 0 && $this->discount_value <= 100) {
                    return ($subtotal * $this->discount_value) / 100;
                }
                break;
            
            case 'fixed':
                if ($this->discount_value > 0) {
                    return min($this->discount_value, $subtotal);
                }
                break;
            
            case 'free_months':
                // Free months are extra delivery months; the customer still pays the full plan period.
                return 0;
        }

        return 0;
    }

    /**
     * Free months granted as a subscription reward.
     */
    public function freeMonths(): int
    {
        if ($this->discount_type === 'free_months' && (int) $this->discount_free_months > 0) {
            return (int) $this->discount_free_months;
        }

        return 0;
    }

    /**
     * Period used for pricing. Free months are extra deliveries, so the customer
     * still pays the full subscription period (e.g. 6 months + 1 free → pay 6).
     */
    public function getEffectivePeriodForPricing(): int
    {
        return $this->getPeriodInMonthsAttribute();
    }

    /**
     * Period used to generate delivery orders (paid months + free months).
     * Example: 6-month plan + 1 free month → pay 6 months, receive 7 months of orders.
     */
    public function getDeliveryPeriodInMonths(): int
    {
        return $this->getPeriodInMonthsAttribute() + $this->freeMonths();
    }
}
