<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Subscription;
use Illuminate\Validation\Rule;

class SubscriptionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the response that should be returned if validation fails.
     */
    public function failedValidation(\Illuminate\Contracts\Validation\Validator $validator)
    {
        throw new \Illuminate\Http\Exceptions\HttpResponseException(
            response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422)
        );
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $subscriptionId = $this->route('id');
        
        $rules = [
            'offer_id' => [
                'required',
                'integer',
                'exists:offers,id',
                function ($attribute, $value, $fail) {
                    $offer = \App\Models\Offer::find($value);
                    if ($offer && !$offer->is_subscription) {
                        $fail('The selected offer must be a subscription offer.');
                    }
                }
            ],
            'period' => [
                'required',
                Rule::in(Subscription::PERIODS),
                // Ensure unique combination of offer_id and period (except for current record on update)
                Rule::unique('subscriptions', 'period')
                    ->where('offer_id', $this->input('offer_id'))
                    ->ignore($subscriptionId)
            ],
            'points' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
            'discount_type' => [
                'nullable',
                Rule::in(Subscription::DISCOUNT_TYPES)
            ],
            'discount_value' => [
                'nullable',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    $discountType = $this->input('discount_type');
                    
                    if ($discountType === 'percentage' && $value > 100) {
                        $fail('The discount percentage cannot exceed 100%.');
                    }
                    
                    if ($discountType === 'percentage' || $discountType === 'fixed') {
                        if (!$value || $value <= 0) {
                            $fail('The discount value is required when discount type is percentage or fixed.');
                        }
                    }
                }
            ],
            'discount_free_months' => [
                'nullable',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $discountType = $this->input('discount_type');
                    $period = (int) $this->input('period');
                    
                    if ($discountType === 'free_months') {
                        if (!$value || $value <= 0) {
                            $fail('The number of free months is required when discount type is free months.');
                        }
                        
                        if ($value >= $period) {
                            $fail('The number of free months must be less than the subscription period.');
                        }
                    }
                }
            ],
        ];

        return $rules;
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation()
    {
        // Set default value for points if not provided
        if (!$this->has('points') || $this->input('points') === null) {
            $this->merge(['points' => 0]);
        }

        // Set default discount type if not provided
        if (!$this->has('discount_type') || $this->input('discount_type') === null) {
            $this->merge(['discount_type' => 'none']);
        }

        // Clear discount_value and discount_free_months if discount_type is 'none'
        if ($this->input('discount_type') === 'none') {
            $this->merge([
                'discount_value' => null,
                'discount_free_months' => null,
            ]);
        }
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'offer_id.required' => 'العرض مطلوب.',
            'offer_id.integer' => 'معرف العرض يجب أن يكون رقم صحيح.',
            'offer_id.exists' => 'العرض المحدد غير موجود.',
            'period.required' => 'فترة الاشتراك مطلوبة.',
            'period.in' => 'فترة الاشتراك يجب أن تكون 3 أو 6 أو 12 شهر.',
            'period.unique' => 'يوجد اشتراك بنفس العرض والفترة مسبقاً.',
            'points.integer' => 'النقاط يجب أن تكون رقم صحيح.',
            'points.min' => 'النقاط يجب أن تكون على الأقل 0.',
            'is_active.boolean' => 'حالة التفعيل يجب أن تكون صحيحة أو خاطئة.',
            'discount_type.in' => 'نوع الخصم يجب أن يكون: بدون خصم، نسبة مئوية، مبلغ ثابت، أو أشهر مجانية.',
            'discount_value.numeric' => 'قيمة الخصم يجب أن تكون رقم.',
            'discount_value.min' => 'قيمة الخصم يجب أن تكون على الأقل 0.',
            'discount_free_months.integer' => 'عدد الأشهر المجانية يجب أن يكون رقم صحيح.',
            'discount_free_months.min' => 'عدد الأشهر المجانية يجب أن يكون على الأقل 1.',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'offer_id' => 'العرض',
            'period' => 'فترة الاشتراك',
            'points' => 'النقاط',
            'is_active' => 'حالة التفعيل',
            'discount_type' => 'نوع الخصم',
            'discount_value' => 'قيمة الخصم',
            'discount_free_months' => 'عدد الأشهر المجانية',
        ];
    }
}
