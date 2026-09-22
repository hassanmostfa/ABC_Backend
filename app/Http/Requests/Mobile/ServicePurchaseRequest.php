<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;

class ServicePurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'service_id' => 'required|integer|exists:services,id',
            'payment_method' => 'nullable|string|in:wallet',
            'src' => 'required_unless:payment_method,wallet|nullable|string|in:knet,cc,wallet',
        ];
    }

    public function messages(): array
    {
        return [
            'service_id.required' => 'Service is required.',
            'service_id.exists' => 'The selected service does not exist.',
            'payment_method.in' => 'Payment method must be wallet.',
            'src.required_unless' => 'Payment source is required for online payment.',
            'src.in' => 'Payment source must be knet, cc, or wallet.',
        ];
    }
}
