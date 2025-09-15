<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class OfferRequest extends FormRequest
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
            'offer_start_date' => 'required|date|after_or_equal:today',
            'offer_end_date' => 'required|date|after:offer_start_date',
            'is_active' => 'boolean',
            'image' => 'nullable|string|max:500',
            'type' => 'nullable|string|max:100',
            'points' => 'nullable|integer|min:0',
            'charity_id' => 'nullable|integer|exists:charities,id',
            'reward_type' => 'required|in:products,discount',
            'conditions' => 'required|array|min:1',
            'conditions.*.product_id' => 'required|integer|exists:products,id',
            'conditions.*.product_variant_id' => 'nullable|integer|exists:product_variants,id',
            'conditions.*.quantity' => 'required|integer|min:1',
            'conditions.*.is_active' => 'boolean',
        ];

        // Add reward validation based on reward_type
        if ($this->input('reward_type') === 'products') {
            $rules['rewards'] = 'required|array|min:1';
            $rules['rewards.*.product_id'] = 'required|integer|exists:products,id';
            $rules['rewards.*.product_variant_id'] = 'nullable|integer|exists:product_variants,id';
            $rules['rewards.*.quantity'] = 'required|integer|min:1';
            $rules['rewards.*.discount_amount'] = 'nullable|numeric|min:0';
            $rules['rewards.*.discount_type'] = 'nullable|in:percentage,fixed';
            $rules['rewards.*.is_active'] = 'boolean';
        } else {
            // For discount type, rewards are optional or can be empty
            $rules['rewards'] = 'nullable|array';
            $rules['rewards.*.product_id'] = 'nullable|integer|exists:products,id';
            $rules['rewards.*.product_variant_id'] = 'nullable|integer|exists:product_variants,id';
            $rules['rewards.*.quantity'] = 'nullable|integer|min:1';
            $rules['rewards.*.discount_amount'] = 'nullable|numeric|min:0';
            $rules['rewards.*.discount_type'] = 'nullable|in:percentage,fixed';
            $rules['rewards.*.is_active'] = 'boolean';
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
            'offer_start_date.required' => 'The offer start date is required.',
            'offer_start_date.date' => 'The offer start date must be a valid date.',
            'offer_start_date.after_or_equal' => 'The offer start date must be today or in the future.',
            'offer_end_date.required' => 'The offer end date is required.',
            'offer_end_date.date' => 'The offer end date must be a valid date.',
            'offer_end_date.after' => 'The offer end date must be after the start date.',
            'is_active.boolean' => 'The is active field must be true or false.',
            'image.max' => 'The image path may not be greater than 500 characters.',
            'type.max' => 'The type may not be greater than 100 characters.',
            'points.integer' => 'The points must be an integer.',
            'points.min' => 'The points must be at least 0.',
            'charity_id.integer' => 'The charity must be a valid ID.',
            'charity_id.exists' => 'The selected charity does not exist.',
            'reward_type.required' => 'The reward type is required.',
            'reward_type.in' => 'The reward type must be either products or discount.',
            'conditions.required' => 'At least one condition is required.',
            'conditions.array' => 'The conditions must be an array.',
            'conditions.min' => 'At least one condition is required.',
            'conditions.*.product_id.required' => 'The condition product is required.',
            'conditions.*.product_id.exists' => 'The selected condition product does not exist.',
            'conditions.*.product_variant_id.exists' => 'The selected condition variant does not exist.',
            'conditions.*.quantity.required' => 'The condition quantity is required.',
            'conditions.*.quantity.min' => 'The condition quantity must be at least 1.',
            'rewards.required' => 'At least one reward is required.',
            'rewards.array' => 'The rewards must be an array.',
            'rewards.min' => 'At least one reward is required.',
            'rewards.*.product_id.required' => 'The reward product is required.',
            'rewards.*.product_id.exists' => 'The selected reward product does not exist.',
            'rewards.*.product_variant_id.exists' => 'The selected reward variant does not exist.',
            'rewards.*.quantity.required' => 'The reward quantity is required.',
            'rewards.*.quantity.min' => 'The reward quantity must be at least 1.',
            'rewards.*.discount_amount.numeric' => 'The discount amount must be a number.',
            'rewards.*.discount_amount.min' => 'The discount amount must be at least 0.',
            'rewards.*.discount_type.in' => 'The discount type must be either percentage or fixed.',
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
            'offer_start_date' => 'offer start date',
            'offer_end_date' => 'offer end date',
            'is_active' => 'is active',
            'image' => 'image',
            'type' => 'type',
            'points' => 'points',
            'charity_id' => 'charity',
            'reward_type' => 'reward type',
            'conditions' => 'conditions',
            'rewards' => 'rewards',
        ];
    }
}
