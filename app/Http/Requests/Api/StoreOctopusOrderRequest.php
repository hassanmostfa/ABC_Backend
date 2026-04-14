<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class StoreOctopusOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

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

    protected function prepareForValidation(): void
    {
        if (!$this->has('src') || $this->input('src') === null) {
            $this->merge(['src' => 'octopus']);
        }
    }

    public function rules(): array
    {
        return [
            'phone' => 'required|string|max:20',
            'name' => 'required|string|max:255',
            'payment_method' => 'required|in:cash,online_link',
            'address' => 'required|string|max:1000',
            'src' => 'nullable|in:knet,cc,octopus',
            
            // Payment info (if online and already paid)
            'payment_info' => 'nullable|array',
            'payment_info.transaction_id' => 'nullable|string|max:255',
            'payment_info.tran_id' => 'nullable|string|max:255',
            'payment_info.track_id' => 'nullable|string|max:255',
            'payment_info.payment_id' => 'nullable|string|max:255',
            'payment_info.receipt_id' => 'nullable|string|max:255',
            'payment_info.paid_at' => 'nullable|date',
            
            // Offers
            'offers' => 'nullable|array',
            'offers.*.offer_id' => 'required_with:offers|integer|exists:offers,id',
            'offers.*.quantity' => 'required_with:offers.*.offer_id|integer|min:1',
            
            // Items (using short_item instead of variant_id)
            'items' => [
                'required_without:offers',
                'nullable',
                'array',
                function ($attribute, $value, $fail) {
                    $offers = $this->input('offers');
                    $hasOffers = !empty($offers) && is_array($offers) && count($offers) > 0;
                    if (!$hasOffers) {
                        if (empty($value) || !is_array($value) || count($value) === 0) {
                            $fail('Items are required when no offers are provided.');
                        }
                    }
                },
            ],
            'items.*.short_item' => 'required_with:items|string|exists:product_variants,short_item',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.string' => 'Phone number must be a string.',
            'phone.max' => 'Phone number may not be greater than 20 characters.',
            'name.required' => 'Customer name is required.',
            'name.string' => 'Customer name must be a string.',
            'name.max' => 'Customer name may not be greater than 255 characters.',
            'payment_method.required' => 'Payment method is required.',
            'payment_method.in' => 'Payment method must be cash or online_link.',
            'address.required' => 'Delivery address is required.',
            'address.string' => 'Delivery address must be a string.',
            'address.max' => 'Delivery address may not be greater than 1000 characters.',
            'src.in' => 'Payment gateway source must be knet, cc, or octopus.',
            'offers.array' => 'Offers must be an array.',
            'offers.*.offer_id.required_with' => 'Offer ID is required.',
            'offers.*.offer_id.integer' => 'Offer ID must be an integer.',
            'offers.*.offer_id.exists' => 'Selected offer does not exist.',
            'offers.*.quantity.required_with' => 'Offer quantity is required.',
            'offers.*.quantity.integer' => 'Offer quantity must be an integer.',
            'offers.*.quantity.min' => 'Offer quantity must be at least 1.',
            'items.array' => 'Items must be an array.',
            'items.*.short_item.required_with' => 'Item short_item code is required.',
            'items.*.short_item.string' => 'Item short_item must be a string.',
            'items.*.short_item.exists' => 'Product variant with this short_item does not exist.',
            'items.*.quantity.required_with' => 'Item quantity is required.',
            'items.*.quantity.integer' => 'Item quantity must be an integer.',
            'items.*.quantity.min' => 'Item quantity must be at least 1.',
        ];
    }

    public function attributes(): array
    {
        return [
            'phone' => 'phone number',
            'name' => 'customer name',
            'payment_method' => 'payment method',
            'address' => 'delivery address',
            'src' => 'payment gateway source',
            'offers' => 'offers',
            'offers.*.offer_id' => 'offer ID',
            'offers.*.quantity' => 'offer quantity',
            'items' => 'order items',
            'items.*.short_item' => 'item short code',
            'items.*.quantity' => 'item quantity',
        ];
    }
}
