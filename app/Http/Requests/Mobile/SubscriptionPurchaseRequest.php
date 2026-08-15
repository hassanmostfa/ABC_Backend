<?php

namespace App\Http\Requests\Mobile;

use Carbon\Carbon;
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
            'payment_method' => 'nullable|string|in:wallet',
            'src' => 'required_unless:payment_method,wallet|nullable|string|in:knet,cc,wallet',
            'source' => 'nullable|string|in:app,web',
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
            'src.required' => 'مصدر الدفع مطلوب',
            'src.required_unless' => 'مصدر الدفع مطلوب للدفع الإلكتروني',
            'src.in' => 'مصدر الدفع يجب أن يكون knet أو cc أو wallet',
            'payment_method.in' => 'طريقة الدفع يجب أن تكون wallet',
            'source.in' => 'المصدر يجب أن يكون app أو web',
        ];
    }

    protected function prepareForValidation(): void
    {
        $source = $this->input('source')
            ?? $this->header('X-Source')
            ?? $this->header('X-Platform');

        if (!is_string($source) || trim($source) === '') {
            return;
        }

        $source = strtolower(trim($source));
        if ($source === 'website') {
            $source = 'web';
        }

        if (in_array($source, ['app', 'web'], true)) {
            $this->merge(['source' => $source]);
        }
    }

    /**
     * Validate monthly delivery template (one month only, repeated for subscription period).
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            if (!$this->has('subscription_id') || !$this->has('orders_per_month')) {
                return;
            }

            $subscription = \App\Models\Subscription::find($this->subscription_id);
            if (!$subscription) {
                return;
            }

            $ordersPerMonth = (int) $this->orders_per_month;
            $providedDates = $this->delivery_schedule ?? [];

            if (count($providedDates) !== $ordersPerMonth) {
                $validator->errors()->add(
                    'delivery_schedule',
                    "يجب توفير {$ordersPerMonth} موعد توصيل لشهر واحد (عدد الطلبات شهرياً). سيتم تكرار نفس الأيام في باقي أشهر الاشتراك."
                );

                return;
            }

            $months = collect($providedDates)
                ->map(fn ($date) => Carbon::parse($date)->format('Y-m'))
                ->unique();

            if ($months->count() > 1) {
                $validator->errors()->add(
                    'delivery_schedule',
                    'يجب أن تكون جميع مواعيد التوصيل في نفس الشهر (قالب شهري واحد).'
                );
            }

            $sorted = collect($providedDates)
                ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
                ->sort()
                ->values()
                ->all();

            $original = collect($providedDates)
                ->map(fn ($date) => Carbon::parse($date)->format('Y-m-d'))
                ->values()
                ->all();

            if ($sorted !== $original) {
                $validator->errors()->add(
                    'delivery_schedule',
                    'يجب ترتيب مواعيد التوصيل تصاعدياً ضمن الشهر.'
                );
            }
        });
    }
}

