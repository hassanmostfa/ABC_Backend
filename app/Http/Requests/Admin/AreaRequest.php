<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AreaRequest extends FormRequest
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
        $areaId = $this->route('area') ?? $this->route('id');
        
        return [
            'name_en' => 'required|string|max:255|unique:areas,name_en,' . $areaId,
            'name_ar' => 'required|string|max:255|unique:areas,name_ar,' . $areaId,
            'governorate_id' => 'required|exists:governorates,id',
            'is_active' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name_en.required' => 'English name is required.',
            'name_en.unique' => 'This English name already exists.',
            'name_ar.required' => 'Arabic name is required.',
            'name_ar.unique' => 'This Arabic name already exists.',
            'governorate_id.required' => 'Governorate is required.',
            'governorate_id.exists' => 'Selected governorate does not exist.',
            'is_active.boolean' => 'Active status must be true or false.',
        ];
    }
}
