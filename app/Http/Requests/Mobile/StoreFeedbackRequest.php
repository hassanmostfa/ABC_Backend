<?php

namespace App\Http\Requests\Mobile;

use App\Models\Feedback;
use App\Models\Order;
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
            'order_number' => [
                'required',
                'string',
                Rule::exists('orders', 'order_number')->where(function ($query) use ($customerId) {
                    $query->where('customer_id', $customerId);
                }),
                function (string $attribute, mixed $value, \Closure $fail) use ($customerId) {
                    $order = Order::query()
                        ->where('order_number', (string) $value)
                        ->where('customer_id', $customerId)
                        ->first();

                    if (!$order) {
                        return;
                    }

                    $alreadySubmitted = Feedback::query()
                        ->where('order_id', $order->id)
                        ->where('customer_id', $customerId)
                        ->exists();

                    if ($alreadySubmitted) {
                        $fail($this->msg('You already submitted feedback for this order.', 'لقد قمت بإرسال تقييم لهذا الطلب من قبل.'));
                    }
                },
            ],
            'rating' => 'required|integer|min:1|max:5',
            'review' => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'order_number.required' => $this->msg('The order number field is required.', 'حقل رقم الطلب مطلوب.'),
            'order_number.string' => $this->msg('The order number must be a valid string.', 'يجب أن يكون رقم الطلب نصاً صالحاً.'),
            'order_number.exists' => $this->msg('The selected order number is invalid.', 'رقم الطلب المحدد غير صالح.'),
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

