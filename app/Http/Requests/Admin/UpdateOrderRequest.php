<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Customer;

class UpdateOrderRequest extends FormRequest
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
        $orderId = $this->route('id');
        
        return [
            'customer_id' => 'sometimes|nullable|integer|exists:customers,id',
            'charity_id' => 'sometimes|nullable|integer|exists:charities,id',
            'customer_address_id' => 'sometimes|nullable|integer|exists:customer_addresses,id',
            'order_number' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('orders', 'order_number')->ignore($orderId)
            ],
            'status' => 'sometimes|required|in:pending,processing,completed,cancelled',
            'offer_ids' => 'sometimes|nullable|array', // Backward compatibility: simple array of IDs
            'offer_ids.*' => 'required_with:offer_ids|integer|exists:offers,id',
            'offers' => 'sometimes|nullable|array', // New format: array of objects with offer_id and quantity
            'offers.*.offer_id' => 'required_with:offers|integer|exists:offers,id',
            'offers.*.quantity' => 'required_with:offers.*.offer_id|integer|min:1',
            'offer_snapshot' => 'sometimes|nullable|array',
            'delivery_type' => 'sometimes|nullable|in:pickup,delivery',
            'payment_method' => 'sometimes|nullable|in:cash,card,online_link,bank_transfer,wallet',
            'used_points' => [
                'sometimes',
                'nullable',
                'integer',
                'min:10',
                function ($attribute, $value, $fail) {
                    if (!$value) {
                        return; // Skip validation if value is null or 0
                    }
                    // Check customer has enough points
                    $customerId = $this->input('customer_id');
                    if (!$customerId) {
                        // Try to get customer_id from existing order
                        $orderId = $this->route('id');
                        if ($orderId) {
                            $order = \App\Models\Order::find($orderId);
                            $customerId = $order ? $order->customer_id : null;
                        }
                    }
                    
                    if ($value && $customerId) {
                        $customer = Customer::find($customerId);
                        if ($customer) {
                            // Get current invoice to check already used points
                            $orderId = $this->route('id');
                            $order = \App\Models\Order::find($orderId);
                            if ($order) {
                                $invoice = $order->invoice;
                                $alreadyUsedPoints = $invoice ? $invoice->used_points : 0;
                                $availablePoints = ($customer->points ?? 0) + $alreadyUsedPoints;
                                
                                if ($availablePoints < $value) {
                                    $fail('Customer does not have enough points. Available: ' . ($customer->points ?? 0));
                                }
                            } else {
                                $customerPoints = $customer->points ?? 0;
                                if ($customerPoints < $value) {
                                    $fail('Customer does not have enough points. Available: ' . $customerPoints);
                                }
                            }
                        } else {
                            $fail('Customer not found.');
                        }
                    } elseif ($value && !$customerId) {
                        $fail('Customer ID is required when using points.');
                    }
                },
            ],
            'items' => [
                'sometimes',
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    // If items are provided, they must not be empty array
                    if (isset($value) && (empty($value) || !is_array($value) || count($value) === 0)) {
                        $fail('Items array cannot be empty if provided.');
                    }
                },
            ],
            'items.*.id' => 'nullable|integer|exists:order_items,id',
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
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
            'customer_id.integer' => 'The customer ID must be a valid integer.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'charity_id.integer' => 'The charity ID must be a valid integer.',
            'charity_id.exists' => 'The selected charity does not exist.',
            'customer_address_id.integer' => 'The customer address ID must be a valid integer.',
            'customer_address_id.exists' => 'The selected customer address does not exist.',
            'order_number.required' => 'The order number is required.',
            'order_number.string' => 'The order number must be a string.',
            'order_number.max' => 'The order number may not be greater than 255 characters.',
            'order_number.unique' => 'The order number has already been taken.',
            'status.required' => 'The status is required.',
            'status.in' => 'The status must be one of: pending, processing, completed, cancelled.',
            'delivery_type.in' => 'The delivery type must be either pickup or delivery.',
            'offer_ids.array' => 'The offer IDs must be an array.',
            'offer_ids.*.integer' => 'Each offer ID must be a valid integer.',
            'offer_ids.*.exists' => 'One or more selected offers do not exist.',
            'offer_snapshot.array' => 'The offer snapshot must be an array.',
            'items.array' => 'The items must be an array.',
            'items.min' => 'At least one order item is required when items are provided.',
            'items.*.id.integer' => 'The item ID must be a valid integer.',
            'items.*.id.exists' => 'The selected order item does not exist.',
            'items.*.variant_id.required' => 'The variant ID is required for each item.',
            'items.*.variant_id.integer' => 'The variant ID must be a valid integer.',
            'items.*.variant_id.exists' => 'The selected variant does not exist.',
            'items.*.quantity.required' => 'The quantity is required for each item.',
            'items.*.quantity.integer' => 'The quantity must be a valid integer.',
            'items.*.quantity.min' => 'The quantity must be at least 1.',
            'used_points.integer' => 'The used points must be a valid integer.',
            'used_points.min' => 'The minimum points to use is 10.',
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
            'customer_address_id' => 'customer address',
            'order_number' => 'order number',
            'status' => 'status',
            'delivery_type' => 'delivery type',
            'offer_ids' => 'offers',
            'offer_snapshot' => 'offer snapshot',
            'items' => 'order items',
            'items.*.id' => 'item ID',
            'items.*.variant_id' => 'variant',
            'items.*.quantity' => 'quantity',
            'used_points' => 'used points',
        ];
    }
}

