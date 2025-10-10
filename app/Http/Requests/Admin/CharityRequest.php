<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class CharityRequest extends FormRequest
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
            'name_en' => 'required|string|max:255',
            'name_ar' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20',
            'country_id' => 'nullable|exists:countries,id',
            'governorate_id' => 'nullable|exists:governorates,id',
            'area_id' => 'nullable|exists:areas,id',
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
            'name_en.required' => 'The English name is required.',
            'name_en.string' => 'The English name must be a string.',
            'name_en.max' => 'The English name may not be greater than 255 characters.',
            'name_ar.required' => 'The Arabic name is required.',
            'name_ar.string' => 'The Arabic name must be a string.',
            'name_ar.max' => 'The Arabic name may not be greater than 255 characters.',
            'phone.string' => 'The phone must be a string.',
            'phone.max' => 'The phone may not be greater than 20 characters.',
            'country_id.exists' => 'The selected country does not exist.',
            'governorate_id.exists' => 'The selected governorate does not exist.',
            'area_id.exists' => 'The selected area does not exist.',
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
            'name_en' => 'English name',
            'name_ar' => 'Arabic name',
            'phone' => 'phone',
            'country_id' => 'country',
            'governorate_id' => 'governorate',
            'area_id' => 'area',
        ];
    }
}
