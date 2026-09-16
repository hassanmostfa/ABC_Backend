<?php

namespace App\Http\Requests\Admin;

class StoreSpecialOrderRequest extends StoreOrderRequest
{
    /**
     * Same payload as a normal call-center order, plus the agreed final price. Payment method is
     * mandatory here because the approval outcome differs per method.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'payment_method' => 'required|in:cash,online_link,wallet',
            'final_price' => 'required|numeric|min:0',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'payment_method.required' => 'The payment method is required for a special order.',
            'payment_method.in' => 'The payment method must be one of: cash, online_link, wallet.',
            'final_price.required' => 'The special final price is required.',
            'final_price.numeric' => 'The special final price must be a number.',
            'final_price.min' => 'The special final price cannot be negative.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'final_price' => 'final price',
            'payment_method' => 'payment method',
        ]);
    }
}
