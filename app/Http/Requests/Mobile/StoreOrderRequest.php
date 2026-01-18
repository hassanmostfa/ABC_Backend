<?php

namespace App\Http\Requests\Mobile;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Customer;
use Illuminate\Support\Facades\Auth;

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
        $customer = Auth::guard('sanctum')->user();
        
        $rules = [
            'charity_id' => 'nullable|integer|exists:charities,id',
            'customer_address_id' => [
                'required',
                'integer',
                'exists:customer_addresses,id',
            ],
            'delivery_type' => 'nullable|in:pickup,delivery',
            'payment_method' => 'nullable|in:cash,card,online_link,bank_transfer,wallet',
            'offer_ids' => 'nullable|array',
            'offer_ids.*' => 'required_with:offer_ids|integer|exists:offers,id',
            'offers' => 'nullable|array',
            'offers.*.offer_id' => 'required_with:offers|integer|exists:offers,id',
            'offers.*.quantity' => 'required_with:offers.*.offer_id|integer|min:1',
            'offer_snapshot' => 'nullable|array',
            'used_points' => [
                'nullable',
                'integer',
                'min:10',
                function ($attribute, $value, $fail) use ($customer) {
                    if (!$value) {
                        return;
                    }
                    if($value && $value % 10 !== 0 && $value > 0) {
                        $fail('Points must be a multiple of 10.');
                    }
                    // Check customer has enough points
                    if ($value && $customer) {
                        $customerPoints = $customer->points ?? 0;
                        if ($customerPoints < $value) {
                            $fail('You do not have enough points. Available: ' . $customerPoints);
                        }
                    } elseif ($value && !$customer) {
                        $fail('Customer ID is required when using points.');
                    }
                },
            ],
            'items' => [
                'required_without_all:offer_ids,offers',
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    $offerIds = $this->input('offer_ids');
                    $offers = $this->input('offers');
                    $hasOffers = (!empty($offerIds) && is_array($offerIds) && count($offerIds) > 0) 
                               || (!empty($offers) && is_array($offers) && count($offers) > 0);
                    if (!$hasOffers) {
                        if (empty($value) || !is_array($value) || count($value) === 0) {
                            $fail('Items are required when no offers are provided.');
                        }
                    }
                },
            ],
            'items.*.variant_id' => 'required_with:items|integer|exists:product_variants,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];

        // Validate customer_address_id belongs to authenticated customer
        if ($customer) {
            $rules['customer_address_id'][] = function ($attribute, $value, $fail) use ($customer) {
                $address = \App\Models\CustomerAddress::find($value);
                if ($address && $address->customer_id !== $customer->id) {
                    $fail('The selected customer address does not belong to you.');
                }
            };
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
            'charity_id.integer' => 'The charity ID must be a valid integer.',
            'charity_id.exists' => 'The selected charity does not exist.',
            'customer_address_id.required' => 'The customer address ID is required.',
            'customer_address_id.integer' => 'The customer address ID must be a valid integer.',
            'customer_address_id.exists' => 'The selected customer address does not exist.',
            'delivery_type.in' => 'The delivery type must be either pickup or delivery.',
            'payment_method.in' => 'The payment method is invalid.',
            'offer_ids.array' => 'The offer IDs must be an array.',
            'offer_ids.*.integer' => 'Each offer ID must be a valid integer.',
            'offer_ids.*.exists' => 'One or more selected offers do not exist.',
            'offers.array' => 'The offers must be an array.',
            'offers.*.offer_id.required' => 'The offer ID is required for each offer.',
            'offers.*.offer_id.integer' => 'Each offer ID must be a valid integer.',
            'offers.*.offer_id.exists' => 'One or more selected offers do not exist.',
            'offers.*.quantity.required' => 'The quantity is required for each offer.',
            'offers.*.quantity.integer' => 'The quantity must be a valid integer.',
            'offers.*.quantity.min' => 'The quantity must be at least 1.',
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
            'charity_id' => 'charity',
            'customer_address_id' => 'customer address',
            'delivery_type' => 'delivery type',
            'payment_method' => 'payment method',
            'offer_snapshot' => 'offer snapshot',
            'offers' => 'offers',
            'offers.*.offer_id' => 'offer ID',
            'offers.*.quantity' => 'quantity',
            'items' => 'order items',
            'items.*.variant_id' => 'variant',
            'items.*.quantity' => 'quantity',
            'used_points' => 'used points',
        ];
    }
}
