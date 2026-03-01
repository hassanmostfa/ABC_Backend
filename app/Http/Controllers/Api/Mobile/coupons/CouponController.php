<?php

namespace App\Http\Controllers\Api\Mobile\coupons;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Mobile\ApplyCouponRequest;
use App\Services\CouponService;
use Illuminate\Http\JsonResponse;

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
        $result = $this->couponService->validateForApplyCode(
            $request->validated('code')
        );

        if (!$result['success']) {
            return $this->errorResponse($result['message'], 400);
        }

        $coupon = $result['coupon'];

        return $this->successResponse([
            'coupon_code' => $coupon->code,
            'discount_type' => $coupon->discount_type,
            'discount_value' => (float) $coupon->discount_value,
        ], 'Coupon applied successfully');
    }
}
