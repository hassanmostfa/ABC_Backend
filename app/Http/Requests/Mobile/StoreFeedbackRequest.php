<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreFeedbackRequest extends MobileFormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $customerId = Auth::guard('sanctum')->id();

        return [
            'order_id' => [
                'required',
                'integer',
                Rule::exists('orders', 'id')->where(function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                }),
                Rule::unique('feedbacks', 'order_id')->where(function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                }),
            ],
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required' => $this->msg('The order field is required.', 'حقل الطلب مطلوب.'),
            'order_id.integer' => $this->msg('The order must be a valid integer.', 'يجب أن يكون الطلب رقماً صحيحاً.'),
            'order_id.exists' => $this->msg('The selected order is invalid.', 'الطلب المحدد غير صالح.'),
            'order_id.unique' => $this->msg('You already submitted feedback for this order.', 'لقد قمت بإرسال تقييم لهذا الطلب من قبل.'),
            'rating.required' => $this->msg('The rating field is required.', 'حقل التقييم مطلوب.'),
            'rating.integer' => $this->msg('The rating must be a valid integer.', 'يجب أن يكون التقييم رقماً صحيحاً.'),
            'rating.min' => $this->msg('The rating must be at least 1.', 'يجب أن يكون التقييم 1 على الأقل.'),
            'rating.max' => $this->msg('The rating may not be greater than 5.', 'يجب ألا يزيد التقييم عن 5.'),
            'review.required' => $this->msg('The review field is required.', 'حقل المراجعة مطلوب.'),
            'review.string' => $this->msg('The review must be a string.', 'يجب أن تكون المراجعة نصاً.'),
            'review.max' => $this->msg('The review may not be greater than 2000 characters.', 'يجب ألا تتجاوز المراجعة 2000 حرف.'),
        ];
    }
}

