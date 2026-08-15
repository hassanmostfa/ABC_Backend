<?php

namespace App\Support;

use App\Models\CustomerSubscription;
use App\Models\Offer;
use App\Models\Subscription;
use App\Models\SubscriptionCheckout;

class SubscriptionPricing
{
    /**
     * @return array{before: float, after: float}
     */
    public static function monthlyPrices(?Offer $offer): array
    {
        if (!$offer) {
            return ['before' => 0.0, 'after' => 0.0];
        }

        $offer->loadMissing([
            'conditions.product',
            'conditions.productVariant',
            'rewards.product',
            'rewards.productVariant',
        ]);

        $conditionProductsTotal = 0.0;
        foreach ($offer->conditions as $condition) {
            $variant = $condition->productVariant;
            $product = $condition->product;
            $unitPrice = $variant
                ? (float) $variant->price
                : ($product ? (float) $product->price : 0.0);
            $conditionProductsTotal += $unitPrice * (int) $condition->quantity;
        }

        $rewardProductsTotal = 0.0;
        foreach ($offer->rewards as $reward) {
            $variant = $reward->productVariant;
            $product = $reward->product;
            $unitPrice = $variant
                ? (float) $variant->price
                : ($product ? (float) $product->price : 0.0);
            $rewardProductsTotal += $unitPrice * (int) $reward->quantity;
        }

        if ($offer->reward_type === 'products') {
            $priceBeforeDiscount = $conditionProductsTotal + $rewardProductsTotal;
            $priceAfterDiscount = $conditionProductsTotal;
        } elseif ($offer->reward_type === 'discount') {
            $priceBeforeDiscount = $conditionProductsTotal;
            $totalDiscount = 0.0;

            foreach ($offer->rewards as $reward) {
                if ($reward->discount_amount && $reward->discount_type) {
                    if ($reward->discount_type === 'percentage') {
                        $totalDiscount += ($priceBeforeDiscount * (float) $reward->discount_amount) / 100;
                    } else {
                        $totalDiscount += (float) $reward->discount_amount;
                    }
                }
            }

            $totalDiscount = min($totalDiscount, $priceBeforeDiscount);
            $priceAfterDiscount = max(0.0, $priceBeforeDiscount - $totalDiscount);
        } else {
            $priceBeforeDiscount = $conditionProductsTotal;
            $priceAfterDiscount = $conditionProductsTotal;
        }

        return [
            'before' => round($priceBeforeDiscount, 3),
            'after' => round($priceAfterDiscount, 3),
        ];
    }

    /**
     * @return array{total_before_price: float, total_after_price: float}
     */
    public static function periodTotals(?Offer $offer, int $periodMonths): array
    {
        $monthly = self::monthlyPrices($offer);
        $months = max(0, $periodMonths);

        return [
            'total_before_price' => round($monthly['before'] * $months, 3),
            'total_after_price' => round($monthly['after'] * $months, 3),
        ];
    }

    public static function paymentMethod(?string $src): ?string
    {
        return match ($src) {
            'knet' => 'knet',
            'cc' => 'credit_card',
            'wallet' => 'wallet',
            'credit_card' => 'credit_card',
            default => $src ?: null,
        };
    }

    /**
     * @return array{total_before_price: float, total_after_price: float, payment_method: string|null}
     */
    public static function forPlan(Subscription $subscription): array
    {
        $totals = self::periodTotals($subscription->offer, (int) $subscription->period);

        return $totals + ['payment_method' => null];
    }

    /**
     * @return array{total_before_price: float, total_after_price: float, payment_method: string|null}
     */
    public static function forCustomerSubscription(CustomerSubscription $customerSubscription): array
    {
        $metadata = is_array($customerSubscription->metadata) ? $customerSubscription->metadata : [];
        $subscription = $customerSubscription->subscription;
        $periodMonths = (int) ($subscription->period ?? 0);

        if (isset($metadata['total_before_price'], $metadata['total_after_price'])) {
            $before = (float) $metadata['total_before_price'];
            $after = (float) $metadata['total_after_price'];
        } elseif ($subscription?->offer) {
            $totals = self::periodTotals($subscription->offer, $periodMonths);
            $before = $totals['total_before_price'];
            $after = $totals['total_after_price'];
        } else {
            $after = (float) $customerSubscription->total_amount;
            $before = $after;
        }

        return [
            'total_before_price' => round($before, 3),
            'total_after_price' => round($after, 3),
            'payment_method' => self::paymentMethod($metadata['payment_gateway_src'] ?? null),
        ];
    }

    /**
     * @return array{total_before_price: float, total_after_price: float, payment_method: string|null}
     */
    public static function forCheckout(SubscriptionCheckout $checkout): array
    {
        $draft = $checkout->draft();

        if (isset($draft['total_before_price'], $draft['total_after_price'])) {
            $before = (float) $draft['total_before_price'];
            $after = (float) $draft['total_after_price'];
        } else {
            $after = (float) ($draft['subtotal'] ?? $checkout->amount_due);
            $before = $after;
        }

        return [
            'total_before_price' => round($before, 3),
            'total_after_price' => round($after, 3),
            'payment_method' => self::paymentMethod($checkout->payment_gateway_src),
        ];
    }
}
