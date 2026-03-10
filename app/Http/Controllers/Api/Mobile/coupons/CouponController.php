<?php

namespace App\Http\Controllers\Api\Mobile\coupons;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ApplyCouponRequest;
use App\Models\Coupon;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class CouponController extends BaseApiController
{
    public function __construct(protected CouponService $couponService)
    {
    }

    /**
     * Apply coupon and return discount value.
     */
    public function apply(ApplyCouponRequest $request): JsonResponse
    {
        $customerId = Auth::guard('sanctum')->id();
        $orderAmount = $request->validated('order_amount');
        $variantIds = $request->validated('variant_ids') ?? [];

        $result = $this->couponService->validateForApplyCode(
            $request->validated('code'),
            $customerId,
            $orderAmount !== null ? (float) $orderAmount : null,
            ['variant_ids' => $variantIds]
        );

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        $coupon = $result['coupon'];

        $payload = [
            'coupon_code' => $coupon->code,
            'coupon_type' => $coupon->type,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'minimum_order_amount' => (float) ($coupon->minimum_order_amount ?? 0),
        ];

        if ($coupon->type === Coupon::TYPE_PRODUCT_VARIANT) {
            $coupon->load('productVariants.product');
            $payload['product_variants'] = $coupon->productVariants->map(function ($variant) {
                $product = $variant->product;
                return [
                    'id' => $variant->id,
                    'product_id' => $variant->product_id,
                    'size' => $variant->size,
                    'short_item' => $variant->short_item,
                    'sku' => $variant->sku,
                    'quantity' => (int) $variant->quantity,
                    'price' => (float) $variant->price,
                    'image' => $variant->image ? url(Storage::disk('public')->url($variant->image)) : null,
                    'is_active' => (bool) $variant->is_active,
                    'product' => $product ? [
                        'id' => $product->id,
                        'name_en' => $product->name_en,
                        'name_ar' => $product->name_ar,
                        'sku' => $product->sku,
                        'is_active' => (bool) $product->is_active,
                    ] : null,
                ];
            })->values()->all();
        }

        return $this->successResponse($payload, 'Coupon applied successfully');
    }
}
