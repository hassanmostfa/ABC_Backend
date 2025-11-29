<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerAddressRequest extends FormRequest
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
            'customer_id' => 'required|integer|exists:customers,id',
            'country_id' => 'required|integer|exists:countries,id',
            'governorate_id' => 'required|integer|exists:governorates,id',
            'area_id' => 'required|integer|exists:areas,id',
            'street' => 'nullable|string|max:255',
            'house' => 'nullable|string|max:255',
            'block' => 'nullable|string|max:255',
            'floor' => 'nullable|string|max:255',
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
            'customer_id.required' => 'The customer ID is required.',
            'customer_id.integer' => 'The customer ID must be a valid integer.',
            'customer_id.exists' => 'The selected customer does not exist.',
            'country_id.required' => 'The country ID is required.',
            'country_id.integer' => 'The country ID must be a valid integer.',
            'country_id.exists' => 'The selected country does not exist.',
            'governorate_id.required' => 'The governorate ID is required.',
            'governorate_id.integer' => 'The governorate ID must be a valid integer.',
            'governorate_id.exists' => 'The selected governorate does not exist.',
            'area_id.required' => 'The area ID is required.',
            'area_id.integer' => 'The area ID must be a valid integer.',
            'area_id.exists' => 'The selected area does not exist.',
            'street.string' => 'The street must be a string.',
            'street.max' => 'The street may not be greater than 255 characters.',
            'house.string' => 'The house must be a string.',
            'house.max' => 'The house may not be greater than 255 characters.',
            'block.string' => 'The block must be a string.',
            'block.max' => 'The block may not be greater than 255 characters.',
            'floor.string' => 'The floor must be a string.',
            'floor.max' => 'The floor may not be greater than 255 characters.',
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
            'country_id' => 'country',
            'governorate_id' => 'governorate',
            'area_id' => 'area',
            'street' => 'street',
            'house' => 'house',
            'block' => 'block',
            'floor' => 'floor',
        ];
    }
}

