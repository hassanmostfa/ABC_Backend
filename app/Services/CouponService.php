<?php

namespace App\Services;

use App\Models\Coupon;
use App\Repositories\CouponRepositoryInterface;
use Illuminate\Support\Facades\DB;

class CouponService
{
    public function __construct(protected CouponRepositoryInterface $couponRepository)
    {
    }

    public function validateAndCalculate(string $code, float $orderAmount): array
    {
        if ($orderAmount <= 0) {
            return [
                'success' => false,
                'message' => 'Order amount must be greater than zero.',
            ];
        }

        $coupon = $this->couponRepository->findByCode($code);
        if (!$coupon) {
            return [
                'success' => false,
                'message' => 'Invalid coupon code.',
            ];
        }

        if (!$coupon->is_active) {
            return [
                'success' => false,
                'message' => 'Coupon is inactive.',
            ];
        }

        $now = now();
        if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
            return [
                'success' => false,
                'message' => 'Coupon is not active yet.',
            ];
        }

        if ($coupon->expires_at && $now->gt($coupon->expires_at)) {
            return [
                'success' => false,
                'message' => 'Coupon has expired.',
            ];
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return [
                'success' => false,
                'message' => 'Coupon usage limit reached.',
            ];
        }

        if ($orderAmount < (float) $coupon->minimum_order_amount) {
            return [
                'success' => false,
                'message' => 'Order amount does not meet the minimum required for this coupon.',
                'minimum_order_amount' => (float) $coupon->minimum_order_amount,
            ];
        }

        $discount = $this->calculateDiscount($coupon, $orderAmount);
        $finalAmount = max(0, $orderAmount - $discount);

        return [
            'success' => true,
            'message' => 'Coupon applied successfully.',
            'coupon' => $coupon,
            'discount_value' => round($discount, 3),
            'order_amount' => round($orderAmount, 3),
            'final_amount' => round($finalAmount, 3),
        ];
    }

    /**
     * Validate coupon by code for apply endpoint (date window + usage limit only).
     */
    public function validateForApplyCode(string $code): array
    {
        return DB::transaction(function () use ($code) {
            $coupon = Coupon::query()
                ->where('code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

            if (!$coupon) {
                return [
                    'success' => false,
                    'message' => 'Invalid coupon code.',
                ];
            }

            $now = now();
            if ($coupon->starts_at && $now->lt($coupon->starts_at)) {
                return [
                    'success' => false,
                    'message' => 'Coupon is not active yet.',
                ];
            }

            if ($coupon->expires_at && $now->gt($coupon->expires_at)) {
                return [
                    'success' => false,
                    'message' => 'Coupon has expired.',
                ];
            }

            if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
                return [
                    'success' => false,
                    'message' => 'Coupon usage limit reached.',
                ];
            }

            $coupon->increment('used_count');
            $coupon->refresh();

            return [
                'success' => true,
                'message' => 'Coupon is valid.',
                'coupon' => $coupon,
            ];
        });
    }

    public function calculateDiscount(Coupon $coupon, float $orderAmount): float
    {
        if ($coupon->discount_type === 'percentage') {
            $discount = ($orderAmount * (float) $coupon->discount_value) / 100;
        } else {
            $discount = (float) $coupon->discount_value;
        }

        if ($coupon->maximum_discount_amount !== null) {
            $discount = min($discount, (float) $coupon->maximum_discount_amount);
        }

        return min($discount, $orderAmount);
    }
}
