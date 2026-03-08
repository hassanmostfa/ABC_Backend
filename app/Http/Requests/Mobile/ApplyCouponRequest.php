<?php

namespace App\Http\Requests\Mobile;

class ApplyCouponRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255',
            'order_amount' => 'nullable|numeric|min:0',
            'variant_ids' => 'nullable|array',
            'variant_ids.*' => 'integer',
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => $this->msg('The code field is required.', 'حقل الكود مطلوب.'),
            'code.string' => $this->msg('The code must be a string.', 'يجب أن يكون الكود نصاً.'),
            'code.max' => $this->msg('The code may not be greater than 255 characters.', 'الكود لا يجوز أن يتجاوز 255 حرفاً.'),
            'order_amount.numeric' => $this->msg('The order amount must be a number.', 'يجب أن يكون مبلغ الطلب رقماً.'),
            'order_amount.min' => $this->msg('The order amount must be at least 0.', 'يجب أن يكون مبلغ الطلب على الأقل 0.'),
            'variant_ids.array' => $this->msg('The variant ids must be an array.', 'يجب أن يكون المتغيرات مصفوفة.'),
            'variant_ids.*.integer' => $this->msg('The variant id must be a valid integer.', 'يجب أن يكون معرف المتغير رقماً صحيحاً.'),
        ];
    }
}
