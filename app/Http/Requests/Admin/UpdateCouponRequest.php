<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = (int) $this->route('id');

        return [
            'code' => 'sometimes|required|string|max:255|unique:coupons,code,' . $couponId,
            'type' => 'sometimes|required|in:general,product_variant,welcome',
            'name' => 'sometimes|nullable|string|max:255',
            'discount_type' => 'sometimes|required|in:percentage,fixed',
            'discount_value' => 'sometimes|required|numeric|min:0.001',
            'minimum_order_amount' => 'sometimes|nullable|numeric|min:0',
            'maximum_discount_amount' => 'sometimes|nullable|numeric|min:0',
            'usage_limit' => 'sometimes|nullable|integer|min:1',
            'starts_at' => 'sometimes|nullable|date',
            'expires_at' => 'sometimes|nullable|date|after_or_equal:starts_at',
            'is_active' => 'sometimes|boolean',
            'product_variant_ids' => 'required_if:type,product_variant|sometimes|array',
            'product_variant_ids.*' => 'integer|exists:product_variants,id',
        ];
    }
}
