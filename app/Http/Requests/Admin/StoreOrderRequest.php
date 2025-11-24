<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Customer;

class StoreOrderRequest extends FormRequest
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
        $rules = [
            'customer_id' => 'nullable|integer|exists:customers,id',
            'charity_id' => 'nullable|integer|exists:charities,id',
            'delivery_type' => 'required|in:pickup,delivery',
            'offer_id' => 'nullable|integer|exists:offers,id',
            'offer_snapshot' => 'nullable|array',
            'used_points' => [
                'nullable',
                'integer',
                'min:10',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return; // Skip validation if value is null or 0
                    }
                    if($value && $value % 10 !== 0 && $value > 0) {
                        $fail('Points must be a multiple of 10.');
                    }
                    // Check customer has enough points
                    if ($value && $this->input('customer_id')) {
                        $customer = Customer::find($this->input('customer_id'));
                        if ($customer) {
                            $customerPoints = $customer->points ?? 0;
                            if ($customerPoints < $value) {
                                $fail('Customer does not have enough points. Available: ' . $customerPoints);
                            }
                        } else {
                            $fail('Customer not found.');
                        }
                    } elseif ($value && !$this->input('customer_id')) {
                        $fail('Customer ID is required when using points.');
                    }
                },
            ],
            'items' => [
                'required_without:offer_id',
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    // If no offer_id, items are required and must not be empty
                    if (!$this->input('offer_id')) {
                        if (empty($value) || !is_array($value) || count($value) === 0) {
                            $fail('Items are required when no offer is provided.');
                        }
                    }
                    // If offer_id is provided, items can be empty or not provided (offer will add items)
                },
            ],
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];

        // Add delivery validation rules when delivery_type is "delivery"
        if ($this->input('delivery_type') === 'delivery') {
            $rules['delivery'] = 'required|array';
            $rules['delivery.payment_method'] = 'required|in:cash,card,online,bank_transfer,wallet';
            $rules['delivery.delivery_address'] = 'required|string';
            $rules['delivery.block'] = 'nullable|string|max:255';
            $rules['delivery.street'] = 'nullable|string|max:255';
            $rules['delivery.house_number'] = 'nullable|string|max:255';
            $rules['delivery.delivery_datetime'] = 'required|date';
            $rules['delivery.received_datetime'] = 'nullable|date';
            $rules['delivery.delivery_status'] = 'nullable|in:pending,assigned,in_transit,delivered,failed,cancelled';
            $rules['delivery.notes'] = 'nullable|string';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'customer_id.integer' => 'The customer ID must be a valid integer.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'charity_id.integer' => 'The charity ID must be a valid integer.',
            'charity_id.exists' => 'The selected charity does not exist.',
            'source.required' => 'The order source is required.',
            'source.in' => 'The order source must be one of: app, web, call_center.',
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be one of: pending, processing, completed, cancelled.',
            'delivery_type.required' => 'The delivery type is required.',
            'delivery_type.in' => 'The delivery type must be either pickup or delivery.',
            'offer_id.integer' => 'The offer ID must be a valid integer.',
            'offer_id.exists' => 'The selected offer does not exist.',
            'offer_snapshot.array' => 'The offer snapshot must be an array.',
            'items.required' => 'At least one order item is required.',
            'items.array' => 'The items must be an array.',
            'items.min' => 'At least one order item is required.',
            'items.*.variant_id.required' => 'The variant ID is required for each item.',
            'items.*.variant_id.integer' => 'The variant ID must be a valid integer.',
            'items.*.variant_id.exists' => 'The selected variant does not exist.',
            'items.*.quantity.required' => 'The quantity is required for each item.',
            'items.*.quantity.integer' => 'The quantity must be a valid integer.',
            'items.*.quantity.min' => 'The quantity must be at least 1.',
            'used_points.integer' => 'The used points must be a valid integer.',
            'used_points.min' => 'The minimum points to use is 10.',
            'delivery.required' => 'Delivery information is required when delivery type is delivery.',
            'delivery.array' => 'Delivery must be an array.',
            'delivery.payment_method.in' => 'The payment method must be one of: cash, card, online, bank_transfer, wallet.',
            'delivery.delivery_address.required' => 'The delivery address is required.',
            'delivery.delivery_address.string' => 'The delivery address must be a string.',
            'delivery.block.string' => 'The block must be a string.',
            'delivery.block.max' => 'The block may not be greater than 255 characters.',
            'delivery.street.string' => 'The street must be a string.',
            'delivery.street.max' => 'The street may not be greater than 255 characters.',
            'delivery.house_number.string' => 'The house number must be a string.',
            'delivery.house_number.max' => 'The house number may not be greater than 255 characters.',
            'delivery.delivery_datetime.date' => 'The delivery datetime must be a valid date.',
            'delivery.received_datetime.date' => 'The received datetime must be a valid date.',
            'delivery.delivery_status.in' => 'The delivery status must be one of: pending, assigned, in_transit, delivered, failed, cancelled.',
            'delivery.notes.string' => 'The notes must be a string.',
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
            'customer_id' => 'customer',
            'charity_id' => 'charity',
            'source' => 'order source',
            'status' => 'status',
            'delivery_type' => 'delivery type',
            'offer_id' => 'offer',
            'offer_snapshot' => 'offer snapshot',
            'items' => 'order items',
            'items.*.variant_id' => 'variant',
            'items.*.quantity' => 'quantity',
            'used_points' => 'used points',
            'delivery' => 'delivery information',
            'delivery.payment_method' => 'payment method',
            'delivery.delivery_address' => 'delivery address',
            'delivery.block' => 'block',
            'delivery.street' => 'street',
            'delivery.house_number' => 'house number',
            'delivery.delivery_datetime' => 'delivery datetime',
            'delivery.received_datetime' => 'received datetime',
            'delivery.delivery_status' => 'delivery status',
            'delivery.notes' => 'notes',
        ];
    }
}

