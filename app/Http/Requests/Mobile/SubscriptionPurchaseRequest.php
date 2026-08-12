<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class SubscriptionPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subscription_id' => 'required|integer|exists:subscriptions,id',
            'orders_per_month' => 'required|integer|min:1|max:4',
            'start_date' => 'nullable|date|after_or_equal:today',
            'delivery_schedule' => 'required|array',
            'delivery_schedule.*' => 'required|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'subscription_id.required' => 'الاشتراك مطلوب',
            'subscription_id.exists' => 'الاشتراك المحدد غير موجود',
            'orders_per_month.required' => 'عدد الطلبات شهرياً مطلوب',
            'orders_per_month.min' => 'يجب أن يكون عدد الطلبات على الأقل 1',
            'orders_per_month.max' => 'عدد الطلبات لا يجب أن يتجاوز 4',
            'delivery_schedule.required' => 'مواعيد التوصيل مطلوبة',
            'delivery_schedule.array' => 'مواعيد التوصيل يجب أن تكون مصفوفة',
        ];
    }

    /**
     * Validate delivery schedule matches total orders
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if ($this->has('subscription_id') && $this->has('orders_per_month')) {
                $subscription = \App\Models\Subscription::find($this->subscription_id);
                if ($subscription) {
                    $expectedOrders = (int)$subscription->period * $this->orders_per_month;
                    $providedDates = count($this->delivery_schedule ?? []);
                    
                    if ($providedDates !== $expectedOrders) {
                        $validator->errors()->add(
                            'delivery_schedule',
                            "يجب توفير {$expectedOrders} موعد توصيل (الفترة: {$subscription->period} شهر × {$this->orders_per_month} طلب/شهر)"
                        );
                    }
                }
            }
        });
    }
}
