<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
        $rules = [
            'customer_id' => 'required|integer|exists:customers,id',
            'country_id' => 'required|integer|exists:countries,id',
            'governorate_id' => 'required|integer|exists:governorates,id',
            'area_id' => 'required|integer|exists:areas,id',
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'type' => ['required', 'string', Rule::in(['apartment', 'house', 'office'])],
            'phone_number' => 'required|string|max:50',
            'additional_directions' => 'nullable|string|max:500',
            'address_label' => 'nullable|string|max:100',
        ];

        $type = $this->input('type');
        if ($type === 'apartment') {
            $rules['building_name'] = 'required|string|max:255';
            $rules['apartment_number'] = 'required|string|max:50';
            $rules['floor'] = 'required|string|max:50';
            $rules['street'] = 'required|string|max:255';
        } elseif ($type === 'house') {
            $rules['house'] = 'required|string|max:255';
            $rules['street'] = 'required|string|max:255';
            $rules['block'] = 'required|string|max:255';
        } elseif ($type === 'office') {
            $rules['building_name'] = 'required|string|max:255';
            $rules['company'] = 'required|string|max:255';
            $rules['floor'] = 'required|string|max:50';
            $rules['street'] = 'required|string|max:255';
            $rules['block'] = 'required|string|max:255';
        }

        return $rules;
    }
}
