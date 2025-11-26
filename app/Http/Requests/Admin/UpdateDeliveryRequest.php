<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDeliveryRequest extends FormRequest
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
            'delivery_address' => 'sometimes|string|max:500',
            'block' => 'sometimes|nullable|string|max:255',
            'street' => 'sometimes|nullable|string|max:255',
            'house_number' => 'sometimes|nullable|string|max:255',
            'delivery_datetime' => 'sometimes|nullable|date',
            'received_datetime' => 'sometimes|nullable|date|after_or_equal:delivery_datetime',
            'delivery_status' => 'sometimes|in:pending,assigned,in_transit,delivered,failed,cancelled',
            'notes' => 'sometimes|nullable|string|max:1000',
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
            'order_id.integer' => 'The order ID must be a valid integer.',
            'order_id.exists' => 'The selected order does not exist.',
            'payment_method.in' => 'The payment method must be one of: cash, card, online, bank_transfer, wallet.',
            'delivery_address.string' => 'The delivery address must be a string.',
            'delivery_address.max' => 'The delivery address may not be greater than 500 characters.',
            'block.max' => 'The block may not be greater than 255 characters.',
            'street.max' => 'The street may not be greater than 255 characters.',
            'house_number.max' => 'The house number may not be greater than 255 characters.',
            'delivery_datetime.date' => 'The delivery datetime must be a valid date.',
            'received_datetime.date' => 'The received datetime must be a valid date.',
            'received_datetime.after_or_equal' => 'The received datetime must be after or equal to delivery datetime.',
            'delivery_status.in' => 'The delivery status must be one of: pending, assigned, in_transit, delivered, failed, cancelled.',
            'notes.max' => 'The notes may not be greater than 1000 characters.',
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
            'order_id' => 'order',
            'payment_method' => 'payment method',
            'delivery_address' => 'delivery address',
            'block' => 'block',
            'street' => 'street',
            'house_number' => 'house number',
            'delivery_datetime' => 'delivery datetime',
            'received_datetime' => 'received datetime',
            'delivery_status' => 'delivery status',
            'notes' => 'notes',
        ];
    }
}

