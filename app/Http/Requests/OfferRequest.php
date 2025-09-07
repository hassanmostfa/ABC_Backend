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
        return [
            'target_product_id' => 'required|integer|exists:products,id',
            'target_quantity' => 'required|integer|min:1',
            'gift_product_id' => 'required|integer|exists:products,id',
            'gift_quantity' => 'required|integer|min:1',
            'offer_start_date' => 'required|date|after_or_equal:today',
            'offer_end_date' => 'required|date|after:offer_start_date',
            'is_active' => 'boolean',
            'image' => 'nullable|string|max:500',
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
            'target_product_id.required' => 'The target product is required.',
            'target_product_id.integer' => 'The target product must be a valid ID.',
            'target_product_id.exists' => 'The selected target product does not exist.',
            'target_quantity.required' => 'The target quantity is required.',
            'target_quantity.integer' => 'The target quantity must be an integer.',
            'target_quantity.min' => 'The target quantity must be at least 1.',
            'gift_product_id.required' => 'The gift product is required.',
            'gift_product_id.integer' => 'The gift product must be a valid ID.',
            'gift_product_id.exists' => 'The selected gift product does not exist.',
            'gift_quantity.required' => 'The gift quantity is required.',
            'gift_quantity.integer' => 'The gift quantity must be an integer.',
            'gift_quantity.min' => 'The gift quantity must be at least 1.',
            'offer_start_date.required' => 'The offer start date is required.',
            'offer_start_date.date' => 'The offer start date must be a valid date.',
            'offer_start_date.after_or_equal' => 'The offer start date must be today or in the future.',
            'offer_end_date.required' => 'The offer end date is required.',
            'offer_end_date.date' => 'The offer end date must be a valid date.',
            'offer_end_date.after' => 'The offer end date must be after the start date.',
            'is_active.boolean' => 'The is active field must be true or false.',
            'image.max' => 'The image path may not be greater than 500 characters.',
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
            'target_product_id' => 'target product',
            'target_quantity' => 'target quantity',
            'gift_product_id' => 'gift product',
            'gift_quantity' => 'gift quantity',
            'offer_start_date' => 'offer start date',
            'offer_end_date' => 'offer end date',
            'is_active' => 'is active',
            'image' => 'image',
        ];
    }
}
