<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class BulkUpdateOrderStatusRequest extends FormRequest
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
            'order_ids' => 'required|array|min:1',
            'order_ids.*' => 'required|integer|distinct',
            'status' => 'required|string|in:pending,processing,completed,cancelled,refund',
            'reason' => 'nullable|string|max:1000',
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
            'order_ids.required' => 'Order IDs are required.',
            'order_ids.array' => 'Order IDs must be an array.',
            'order_ids.min' => 'Order IDs must contain at least one order ID.',
            'order_ids.*.required' => 'Each order ID is required.',
            'order_ids.*.integer' => 'Each order ID must be a valid integer.',
            'order_ids.*.distinct' => 'Order IDs must not contain duplicates.',
            'status.required' => 'Status is required.',
            'status.in' => 'Status must be one of: pending, processing, completed, cancelled, refund.',
            'reason.string' => 'Reason must be a string.',
            'reason.max' => 'Reason may not be greater than 1000 characters.',
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
            'order_ids' => 'order IDs',
            'order_ids.*' => 'order ID',
            'status' => 'status',
            'reason' => 'reason',
        ];
    }
}
