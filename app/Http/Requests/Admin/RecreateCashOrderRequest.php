<?php

namespace App\Http\Requests\Admin;

class RecreateCashOrderRequest extends StoreOrderRequest
{
    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'order_number' => 'required|string|max:100|exists:orders,order_number',
            'reason' => 'nullable|string|max:1000',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(parent::messages(), [
            'order_number.required' => 'The order number is required.',
            'order_number.exists' => 'The selected order number does not exist.',
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return array_merge(parent::attributes(), [
            'order_number' => 'order number',
            'reason' => 'reason',
        ]);
    }
}
