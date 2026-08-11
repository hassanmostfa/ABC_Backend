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
        ];
    }
}
