<?php

namespace App\Http\Controllers\Api\Mobile\coupons;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ApplyCouponRequest;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

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

        return $this->successResponse([
            'coupon_code' => $coupon->code,
            'coupon_type' => $coupon->type,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
            'minimum_order_amount' => (float) ($coupon->minimum_order_amount ?? 0),
        ], 'Coupon applied successfully');
    }
}
