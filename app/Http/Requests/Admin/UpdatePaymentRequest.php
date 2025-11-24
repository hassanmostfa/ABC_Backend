<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'invoice_id' => 'sometimes|integer|exists:invoices,id',
            'amount' => 'sometimes|numeric|min:0.01',
            'method' => 'sometimes|in:cash,card,online,bank_transfer,wallet',
            'status' => 'sometimes|in:pending,completed,failed,refunded',
            'paid_at' => 'nullable|date',
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'invoice_id.integer' => 'The invoice ID must be a valid integer.',
            'invoice_id.exists' => 'The selected invoice does not exist.',
            'amount.numeric' => 'The amount must be a valid number.',
            'amount.min' => 'The amount must be at least 0.01.',
            'method.in' => 'The payment method must be one of: cash, card, online, bank_transfer, wallet.',
            'status.in' => 'The status must be one of: pending, completed, failed, refunded.',
            'paid_at.date' => 'The paid at must be a valid date.',
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
            'invoice_id' => 'invoice',
            'amount' => 'payment amount',
            'method' => 'payment method',
            'status' => 'payment status',
            'paid_at' => 'paid at',
        ];
    }
}

