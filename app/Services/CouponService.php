<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\Customer;
use App\Models\Setting;
use App\Repositories\CouponRepositoryInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CouponService
{
    public function __construct(protected CouponRepositoryInterface $couponRepository)
    {
    }

    public function validateAndCalculate(string $code, float $orderAmount, ?int $customerId = null): array
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

        if ($coupon->customer_id !== null) {
            if ($customerId === null || (int) $coupon->customer_id !== (int) $customerId) {
                return [
                    'success' => false,
                    'message' => 'This coupon is not valid for your account.',
                ];
            }
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
     * Validate coupon by code for apply endpoint (date window + usage limit + optional customer and min order).
     *
     * @param  array{variant_ids?: int[]}  $orderContext  Optional: variant_ids for product_variant coupon, order_amount for min check
     */
    public function validateForApplyCode(string $code, ?int $customerId = null, ?float $orderAmount = null, array $orderContext = []): array
    {
        return DB::transaction(function () use ($code, $customerId, $orderAmount, $orderContext) {
            $coupon = Coupon::query()
                ->with('productVariants')
                ->where('code', strtoupper(trim($code)))
                ->lockForUpdate()
                ->first();

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

            if ($coupon->customer_id !== null) {
                if ($customerId === null || (int) $coupon->customer_id !== (int) $customerId) {
                    return [
                        'success' => false,
                        'message' => 'This coupon is not valid for your account.',
                    ];
                }
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

            if ($orderAmount !== null && $orderAmount > 0 && $orderAmount < (float) $coupon->minimum_order_amount) {
                return [
                    'success' => false,
                    'message' => 'Order amount does not meet the minimum required for this coupon.',
                    'minimum_order_amount' => (float) $coupon->minimum_order_amount,
                ];
            }

            if ($coupon->type === Coupon::TYPE_PRODUCT_VARIANT) {
                $variantIds = $orderContext['variant_ids'] ?? [];
                $allowedIds = $coupon->productVariants->pluck('id')->all();
                if (!empty($allowedIds) && empty(array_intersect($variantIds, $allowedIds))) {
                    return [
                        'success' => false,
                        'message' => 'This coupon applies only to specific products. Add an eligible product to your cart.',
                    ];
                }
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

    /**
     * Create a welcome coupon for a newly registered customer (valid for one month, one-time use).
     * Returns null if welcome coupon is disabled in settings.
     */
    public function createWelcomeCouponForCustomer(Customer $customer): ?Coupon
    {
        $enabled = Setting::getValue('welcome_coupon_enabled', '1');
        if ($enabled !== '1' && $enabled !== 1) {
            return null;
        }

        $code = 'WELCOME' . strtoupper(Str::random(6));
        while (Coupon::where('code', $code)->exists()) {
            $code = 'WELCOME' . strtoupper(Str::random(6));
        }

        $discountType = Setting::getValue('welcome_coupon_discount_type', 'percentage');
        $discountValue = (float) Setting::getValue('welcome_coupon_discount_value', '10');
        $minimumOrderAmount = (float) Setting::getValue('welcome_coupon_minimum_order_amount', '0');
        if (! in_array($discountType, ['percentage', 'fixed'], true)) {
            $discountType = 'percentage';
        }

        $coupon = $this->couponRepository->create([
            'code' => $code,
            'type' => Coupon::TYPE_WELCOME,
            'name' => 'Welcome coupon - ' . $customer->name,
            'discount_type' => $discountType,
            'discount_value' => $discountValue,
            'minimum_order_amount' => $minimumOrderAmount,
            'maximum_discount_amount' => null,
            'usage_limit' => 1,
            'used_count' => 0,
            'starts_at' => now(),
            'expires_at' => now()->addMonth(),
            'is_active' => true,
            'customer_id' => $customer->id,
        ]);

        $discountText = $discountType === 'percentage'
            ? $discountValue . '%'
            : $discountValue . ' KWD';
        $expiresAt = $coupon->expires_at?->format('Y-m-d') ?? '';

        $messageEn = 'Your welcome coupon code: ' . $coupon->code . '. Discount: ' . $discountText . '. Valid for one month. Use it at checkout!';
        $messageAr = 'كود كوبون الترحيب الخاص بك: ' . $coupon->code . '. الخصم: ' . $discountText . '. صالح لمدة شهر. استخدمه عند الدفع!';

        sendNotification(
            null,
            $customer->id,
            'Welcome coupon',
            $messageEn,
            'welcome_coupon',
            [
                'coupon_code' => $coupon->code,
                'coupon_id' => (string) $coupon->id,
                'discount_type' => $discountType,
                'discount_value' => (string) $discountValue,
                'expires_at' => $expiresAt,
            ],
            'كوبون الترحيب',
            $messageAr,
            'Welcome coupon',
            $messageEn
        );

        return $coupon;
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
